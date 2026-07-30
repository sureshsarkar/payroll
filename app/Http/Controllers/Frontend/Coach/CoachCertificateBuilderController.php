<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\CertificateBuilder\app\Http\Requests\CertificateUpdateRequest;
use Modules\CertificateBuilder\app\Models\CertificateBuilder;
use Modules\CertificateBuilder\app\Models\CertificateBuilderItem;

/**
 * 2026-06-12 — Per-coach certificate builder (coach panel).
 *
 * Every read/write is scoped to the acting coach's coach_id, so a coach can
 * only ever design THEIR OWN certificate. The platform-wide template
 * (coach_id NULL, admin-managed) is never touched here and remains the fallback
 * for coaches who haven't customised one.
 */
class CoachCertificateBuilderController extends Controller
{
    /** Real coach for this request (staff resolve to their parent coach). */
    private function coachId(): int
    {
        $u = userAuth();

        return (int) ($u->role === 'instructor' ? $u->id : ($u->coach_id ?? $u->id));
    }

    public function index()
    {
        $coachId = $this->coachId();

        // Composed template (coach's wording + global artwork fallback) so the
        // preview never shows an empty box; falls back entirely to global before
        // the coach's first save.
        $certificate = CertificateBuilder::forCoach($coachId) ?? new CertificateBuilder();
        $certificateItems = CertificateBuilderItem::forCoach($coachId);

        // 2026-07-09 — the live preview mirrors the exported certificate, so the
        // Enterprise preview needs the same brand identity the PDF uses.
        $brand = app(\App\Services\BrandResolver::class)->forCoach($coachId);
        $brandColor = $brand->primaryColor ?: '#0f766e';
        $brandName  = $brand->name ?: (\Illuminate\Support\Facades\Cache::get('setting')->app_name ?? 'Academy');
        $brandLogo  = ($brand->ownLogo && $brand->logoPath) ? $brand->logoUrl() : null;
        $coachName  = userAuth()->name ?? $brandName;

        // 2026-07-09 — advanced enterprise options: which DESIGN (classic/modern/
        // royal) and an optional accent-colour override (falls back to the brand
        // colour). Old rows have no template → default 'classic'.
        $certTemplate = in_array($certificate->certificate_template ?? 'classic', ['classic', 'modern', 'royal'], true)
            ? ($certificate->certificate_template ?? 'classic') : 'classic';
        $accentColor = $certificate->accent_color ?: '';

        // 2026-07-09 — typography & layout (DomPDF-safe font key + alignment +
        // optional paper-colour override). Old rows fall back to safe defaults.
        $certFont  = in_array($certificate->font_family ?? 'serif', ['serif', 'sans', 'mono'], true)
            ? ($certificate->font_family ?? 'serif') : 'serif';
        $certAlign = in_array($certificate->text_align ?? 'center', ['center', 'left'], true)
            ? ($certificate->text_align ?? 'center') : 'center';
        $paperColor = $certificate->paper_color ?: '';

        return view('frontend.instructor-dashboard.certificate-builder.index', compact(
            'certificate', 'certificateItems', 'brandColor', 'brandName', 'brandLogo', 'coachName',
            'certTemplate', 'accentColor', 'certFont', 'certAlign', 'paperColor'
        ));
    }

    public function update(CertificateUpdateRequest $request)
    {
        $coachId = $this->coachId();

        $certificate = CertificateBuilder::firstOrNew(['coach_id' => $coachId]);
        $isNew = ! $certificate->exists;

        $certificate->fill($request->only(['title', 'sub_title', 'description']));
        // 2026-07-08 — enterprise (framed + sealed + verifiable) is the default;
        // classic keeps the legacy drag/background template.
        $certificate->certificate_style = in_array($request->input('certificate_style'), ['enterprise', 'classic'], true)
            ? $request->input('certificate_style')
            : ($certificate->certificate_style ?? 'enterprise');
        // 2026-07-09 — enterprise DESIGN variant + optional accent-colour override.
        $certificate->certificate_template = in_array($request->input('certificate_template'), ['classic', 'modern', 'royal'], true)
            ? $request->input('certificate_template')
            : ($certificate->certificate_template ?? 'classic');
        $accent = trim((string) $request->input('accent_color'));
        // Keep only a valid #RGB / #RRGGBB hex; blank ⇒ null ⇒ use brand colour.
        $certificate->accent_color = preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $accent) ? $accent : null;
        // 2026-07-09 — typography & layout (all DomPDF-safe / bounded values).
        $certificate->font_family = in_array($request->input('font_family'), ['serif', 'sans', 'mono'], true)
            ? $request->input('font_family') : ($certificate->font_family ?? 'serif');
        $certificate->text_align = in_array($request->input('text_align'), ['center', 'left'], true)
            ? $request->input('text_align') : ($certificate->text_align ?? 'center');
        $paper = trim((string) $request->input('paper_color'));
        $certificate->paper_color = preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $paper) ? $paper : null;
        $certificate->coach_id = $coachId;

        if ($request->hasFile('background')) {
            $certificate->background = file_upload($request->file('background'), 'uploads/custom-images/', $certificate->background);
        }
        if ($request->hasFile('signature')) {
            $certificate->signature = file_upload($request->file('signature'), 'uploads/custom-images/', $certificate->signature);
        }

        $certificate->save();

        // On first save, copy the global element positions so the coach starts
        // from the default layout instead of an empty canvas.
        if ($isNew && ! CertificateBuilderItem::where('coach_id', $coachId)->exists()) {
            foreach (CertificateBuilderItem::whereNull('coach_id')->get() as $g) {
                CertificateBuilderItem::create([
                    'coach_id'   => $coachId,
                    'element_id' => $g->element_id,
                    'x_position' => $g->x_position,
                    'y_position' => $g->y_position,
                ]);
            }
        }

        return redirect()->back()->with(['messege' => __('Certificate updated successfully'), 'alert-type' => 'success']);
    }

    /**
     * 2026-07-09 — Reset the Classic drag layout to clean, non-overlapping
     * defaults for THIS coach. Fixes a messy layout (e.g. text dragged over the
     * header) in one click without touching content, artwork or any other coach.
     * Text rows render horizontally centred, so only the vertical position
     * matters for title/sub_title/description; the signature is free on both axes.
     */
    public function resetLayout(Request $request)
    {
        $coachId = $this->coachId();

        $defaults = [
            'title'       => ['x' => 0,   'y' => 210],
            'sub_title'   => ['x' => 0,   'y' => 245],
            'description' => ['x' => 0,   'y' => 281],
            'signature'   => ['x' => 398, 'y' => 392],
        ];

        foreach ($defaults as $element => $pos) {
            CertificateBuilderItem::updateOrCreate(
                ['coach_id' => $coachId, 'element_id' => $element],
                ['x_position' => $pos['x'], 'y_position' => $pos['y']]
            );
        }

        if ($request->expectsJson()) {
            return response(['status' => 'success', 'message' => __('Layout reset to default'), 'positions' => $defaults]);
        }

        return redirect()->back()->with(['messege' => __('Layout reset to default'), 'alert-type' => 'success']);
    }

    public function updateItem(Request $request)
    {
        $request->validate([
            'element_id' => ['required', 'string', 'max:64'],
            'x_position' => ['required'],
            'y_position' => ['required'],
        ]);

        CertificateBuilderItem::updateOrCreate(
            ['coach_id' => $this->coachId(), 'element_id' => $request->element_id],
            ['x_position' => $request->x_position, 'y_position' => $request->y_position]
        );

        return response(['status' => 'success', 'message' => __('Updated successfully')]);
    }
}
