<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use App\Models\TaxProfile;
use App\Models\TaxRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Order\app\Models\Order;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Per-coach Tax Settings (Phase 1, 2026-06-13).
 *
 * A coach manages their OWN tax profile (enable, inclusive/exclusive mode,
 * compliance identity) + their OWN named rates. Everything is scoped to the
 * coach's id so one coach can never read/modify another's tax setup. Tax stays
 * off until the coach enables it — see [[TaxService]].
 */
class CoachTaxController extends Controller
{
    /** Resolve the owning coach (a top-level instructor; staff inherit their coach). */
    private function coachId(): int
    {
        return userAuth()->role === 'instructor' ? (int) userAuth()->id : (int) userAuth()->coach_id;
    }

    public function index()
    {
        $coachId = $this->coachId();
        $profile = TaxProfile::firstOrNew(['coach_id' => $coachId]);
        $rates = TaxRate::where('coach_id', $coachId)->orderByDesc('is_default')->orderBy('id')->get();

        return view('frontend.instructor-dashboard.tax.index', compact('profile', 'rates'));
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'mode'                => ['required', 'in:exclusive,inclusive'],
            'legal_name'          => ['nullable', 'string', 'max:191'],
            'registration_label'  => ['nullable', 'string', 'max:60'],
            'registration_number' => ['nullable', 'string', 'max:60'],
            'country'             => ['nullable', 'string', 'max:80'],
            'state'               => ['nullable', 'string', 'max:80'],
            'invoice_note'        => ['nullable', 'string', 'max:191'],
        ]);

        TaxProfile::updateOrCreate(
            ['coach_id' => $this->coachId()],
            [
                'is_enabled'          => $request->boolean('is_enabled'),
                'mode'                => $request->mode,
                'legal_name'          => $request->legal_name,
                'registration_label'  => $request->registration_label ?: 'GSTIN',
                'registration_number' => $request->registration_number,
                'country'             => $request->country,
                'state'               => $request->state,
                'invoice_note'        => $request->invoice_note,
            ]
        );

        return back()->with(['messege' => __('Tax settings saved'), 'alert-type' => 'success']);
    }

    public function storeRate(Request $request)
    {
        $request->validate([
            'name'       => ['required', 'string', 'max:80'],
            'rate'       => ['required', 'numeric', 'min:0', 'max:100'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $coachId = $this->coachId();
        [$components, $totalRate] = $this->parseComponents($request->components, (float) $request->rate);
        DB::transaction(function () use ($request, $coachId, $components, $totalRate) {
            $rate = TaxRate::create([
                'coach_id'   => $coachId,
                'name'       => $request->name,
                'rate'       => $totalRate,
                'components' => $components,
                'is_default' => $request->boolean('is_default'),
                'status'     => 'active',
            ]);
            if ($rate->is_default) {
                $this->makeSoleDefault($coachId, $rate->id);
            }
        });

        return back()->with(['messege' => __('Tax rate added'), 'alert-type' => 'success']);
    }

    public function updateRate(Request $request, $id)
    {
        $request->validate([
            'name'       => ['required', 'string', 'max:80'],
            'rate'       => ['required', 'numeric', 'min:0', 'max:100'],
            'status'     => ['required', 'in:active,inactive'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $coachId = $this->coachId();
        $rate = TaxRate::where('coach_id', $coachId)->where('id', $id)->firstOrFail(); // tenant guard

        [$components, $totalRate] = $this->parseComponents($request->components, (float) $request->rate);
        DB::transaction(function () use ($request, $rate, $coachId, $components, $totalRate) {
            $rate->update([
                'name'       => $request->name,
                'rate'       => $totalRate,
                'components' => $components,
                'status'     => $request->status,
                'is_default' => $request->boolean('is_default'),
            ]);
            if ($rate->is_default) {
                $this->makeSoleDefault($coachId, $rate->id);
            }
        });

        return back()->with(['messege' => __('Tax rate updated'), 'alert-type' => 'success']);
    }

    public function destroyRate($id)
    {
        $coachId = $this->coachId();
        $rate = TaxRate::where('coach_id', $coachId)->where('id', $id)->firstOrFail();
        // Detach from any of this coach's courses so we never leave a dangling FK.
        \App\Models\Course::where('instructor_id', $coachId)->where('tax_rate_id', $rate->id)
            ->update(['tax_rate_id' => null]);
        $rate->delete();

        return back()->with(['messege' => __('Tax rate deleted'), 'alert-type' => 'success']);
    }

    /**
     * Parse the components text ("CGST:9, SGST:9") into a normalised array and
     * the effective total rate. When components are given the total rate is the
     * SUM of their rates (so the % field and the split always agree). Empty input
     * → no components, total = the typed rate (a plain single rate).
     *
     * @return array{0: array<int,array{name:string,rate:float}>|null, 1: float}
     */
    private function parseComponents(?string $raw, float $fallbackRate): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [null, $fallbackRate];
        }

        $components = [];
        $total = 0.0;
        foreach (preg_split('/[,\n]+/', $raw) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            // "CGST:9" or "CGST 9" or "CGST=9"
            if (preg_match('/^(.+?)[\s:=]+([\d.]+)\s*%?$/', $part, $m)) {
                $rate = round((float) $m[2], 3);
                $components[] = ['name' => trim($m[1]), 'rate' => $rate];
                $total += $rate;
            }
        }

        return empty($components) ? [null, $fallbackRate] : [$components, round($total, 3)];
    }

    /** Exactly one default per coach. */
    private function makeSoleDefault(int $coachId, int $rateId): void
    {
        TaxRate::where('coach_id', $coachId)->where('id', '!=', $rateId)->update(['is_default' => false]);
    }

    /**
     * Tax-collected report (Phase 2, 2026-06-13) — the records a coach needs for
     * filing / audit. Scoped to PAID orders that contain THIS coach's courses
     * (same ownership scope as My Sales), with tax actually charged. Date-range
     * filterable + CSV export. Read-only.
     */
    public function report(Request $request)
    {
        $coachId = $this->coachId();
        $from = $request->date('from') ?: now()->startOfMonth();
        $to = ($request->date('to') ?: now())->endOfDay();

        $base = Order::query()
            ->where('payment_status', 'paid')
            ->where('tax_amount', '>', 0)
            ->whereBetween('created_at', [$from, $to])
            ->whereHas('orderItems.course', fn ($q) => $q->where('instructor_id', $coachId)); // tenant scope

        if ($request->get('export') === 'csv') {
            return $this->exportCsv((clone $base)->with('user:id,name,email')->orderByDesc('id')->get(), $from, $to);
        }

        $totals = (clone $base)
            ->selectRaw('COUNT(*) cnt, COALESCE(SUM(taxable_amount),0) taxable, COALESCE(SUM(tax_amount),0) tax')
            ->first();

        $byRate = (clone $base)
            ->selectRaw('tax_label, tax_rate_applied, tax_mode, COUNT(*) cnt, COALESCE(SUM(taxable_amount),0) taxable, COALESCE(SUM(tax_amount),0) tax')
            ->groupBy('tax_label', 'tax_rate_applied', 'tax_mode')
            ->orderByDesc('tax')
            ->get();

        $orders = (clone $base)->with('user:id,name,email')
            ->orderByDesc('id')->paginate(25)->withQueryString();

        return view('frontend.instructor-dashboard.tax.report', compact('totals', 'byRate', 'orders', 'from', 'to'));
    }

    private function exportCsv($orders, $from, $to): StreamedResponse
    {
        $filename = 'tax-report-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($orders) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Invoice', 'Date', 'Student', 'Email', 'Tax Label', 'Rate %', 'Mode', 'Taxable', 'Tax', 'Total']);
            foreach ($orders as $o) {
                fputcsv($out, [
                    $o->invoice_id,
                    optional($o->created_at)->format('Y-m-d'),
                    optional($o->user)->name ?? '—',
                    optional($o->user)->email ?? '',
                    $o->tax_label,
                    rtrim(rtrim(number_format((float) $o->tax_rate_applied, 3), '0'), '.'),
                    $o->tax_mode,
                    number_format((float) $o->taxable_amount, 2, '.', ''),
                    number_format((float) $o->tax_amount, 2, '.', ''),
                    number_format((float) $o->taxable_amount + (float) $o->tax_amount, 2, '.', ''),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
