<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachBrandSetting;
use App\Services\BrandResolver;
use App\Services\CoachMailer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Per-coach white-label brand settings page (P1).
 *
 * GET  /instructor/brand-settings
 * POST /instructor/brand-settings
 *
 * Coach uploads their logo + favicon, sets brand name, colors,
 * support contact, T&C URLs, footer text, email signature. Anything
 * left blank automatically inherits the platform default via
 * BrandResolver.
 *
 * Permission: instructor role only. Coach staff/teachers don't see
 * this page in their sidebar (it shapes the brand for the entire
 * coach's students, not for the staff member).
 */
class CoachBrandSettingController extends Controller
{
    protected string $uploadDir = 'uploads/coach-brand';

    public function edit(): View
    {
        abort_unless(userAuth()?->role === 'instructor', 403);

        $coachId = (int) userAuth()->id;
        $row     = CoachBrandSetting::firstOrCreateForCoach($coachId);
        $brand   = app(BrandResolver::class)->forCoach($coachId);

        // P5 — coach's custom + subdomain hostnames. Each row carries
        // a stable verification token so the coach can copy-paste the
        // TXT record into their DNS without us having to remember
        // anything.
        $domains = \App\Models\CoachDomain::where('coach_id', $coachId)
            ->orderBy('kind')      // subdomain rows first (auto-verified)
            ->orderBy('id')
            ->get();
        $domainCtrl = app(\App\Http\Controllers\Frontend\Coach\CoachDomainController::class);
        $domainTokens = $domains->mapWithKeys(fn ($d) => [$d->id => $domainCtrl->tokenFor($d)])->all();

        // 2026-06-06 — Instant-subdomain self-service data for the UI.
        // $platformHost is the parent we own (e.g. mbsguru.com); the coach's
        // subdomain row (if any) is <label>.<platformHost>. $platformIp /
        // $platformCnameTarget tell a custom-domain coach the ONE record they
        // need to point at us — no TXT step.
        $subdomainRow = $domains->firstWhere('kind', 'subdomain');
        $platformHost = strtolower(trim((string) config('app.coach_domain', '')));
        if ($platformHost === 'localhost') {
            $platformHost = '';
        }
        // 2026-06-09 — server IP + feature flags come from the superadmin
        // settings (falls back to config/env). The IP shown to the coach is
        // authoritative for verification too.
        $cdSettings = app(\App\Services\CustomDomainSettings::class);
        $platformIp = $cdSettings->serverIp();
        $platformCnameTarget = $platformHost;
        // Current label inside the coach's subdomain (for prefilling the input).
        $subdomainSlug = $subdomainRow && $platformHost !== ''
            ? (string) Str::beforeLast($subdomainRow->hostname, '.' . $platformHost)
            : '';

        // Custom-domain feature gating for the UI.
        $customDomainEnabled = $cdSettings->enabled();
        $customDomainMax     = $cdSettings->maxPerCoach();
        $customDomainCount   = $domains->where('kind', 'custom')->count();

        return view('frontend.instructor-dashboard.brand-settings.edit',
            compact('row', 'brand', 'domains', 'domainTokens',
                'subdomainRow', 'subdomainSlug', 'platformHost', 'platformIp', 'platformCnameTarget',
                'customDomainEnabled', 'customDomainMax', 'customDomainCount'));
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(userAuth()?->role === 'instructor', 403);

        $request->validate([
            'brand_name'      => ['nullable', 'string', 'max:80'],
            'primary_color'   => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/'],
            'accent_color'    => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/'],
            'support_email'   => ['nullable', 'email', 'max:120'],
            'support_phone'   => ['nullable', 'string', 'max:40'],
            'terms_url'       => ['nullable', 'url', 'max:500'],
            'privacy_url'     => ['nullable', 'url', 'max:500'],
            'footer_text'     => ['nullable', 'string', 'max:255'],
            'email_signature' => ['nullable', 'string', 'max:2000'],
            // P3 — per-coach email config
            'mail_from_address' => ['nullable', 'email', 'max:120'],
            'mail_from_name'    => ['nullable', 'string', 'max:120'],
            'mail_reply_to'     => ['nullable', 'email', 'max:120'],
            'smtp_host'         => ['nullable', 'string', 'max:200'],
            'smtp_port'         => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username'     => ['nullable', 'string', 'max:200'],
            // Password field — empty = keep existing (don't overwrite
            // with NULL if the coach just edits other fields).
            'smtp_password'     => ['nullable', 'string', 'max:200'],
            'smtp_encryption'   => ['nullable', 'in:none,ssl,tls'],
            // 2 MB caps — coach logos are typically <200KB; cap keeps
            // a misconfigured upload from filling disk.
            // FT-UPLOAD-1 fix (2026-05-27) — dropped svg from BOTH lists.
            // SVG is XML and can carry <script>, event handlers, or
            // javascript: URIs; serving it as the coach's logo would
            // execute attacker JS in every visitor's browser on the
            // coach's white-label site. Coaches uploading vector logos
            // should export PNG @ 2x resolution instead.
            'logo'            => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'favicon'         => ['nullable', 'image', 'mimes:png,ico', 'max:512'],
            'clear_logo'      => ['nullable', 'boolean'],
            'clear_favicon'   => ['nullable', 'boolean'],
            'clear_smtp'      => ['nullable', 'boolean'],
        ], [
            'primary_color.regex' => __('Primary color must be a hex like #6366f1.'),
            'accent_color.regex'  => __('Accent color must be a hex like #8b5cf6.'),
        ]);

        $coachId = (int) userAuth()->id;
        $row = CoachBrandSetting::firstOrCreateForCoach($coachId);

        // Handle file removes BEFORE uploads so user can swap in one save.
        if ($request->boolean('clear_logo')) {
            $this->deletePrevious($row->logo_path);
            $row->logo_path = null;
        }
        if ($request->boolean('clear_favicon')) {
            $this->deletePrevious($row->favicon_path);
            $row->favicon_path = null;
        }

        if ($request->hasFile('logo')) {
            $this->deletePrevious($row->logo_path);
            $row->logo_path = $this->store($request->file('logo'), $coachId, 'logo');
        }
        if ($request->hasFile('favicon')) {
            $this->deletePrevious($row->favicon_path);
            $row->favicon_path = $this->store($request->file('favicon'), $coachId, 'fav');
        }

        // Empty-string from the form means "clear this field" — store as
        // NULL so the resolver falls back to platform default again.
        $row->fill([
            'brand_name'        => $this->nullify($request->input('brand_name')),
            'primary_color'     => $this->nullify($request->input('primary_color')),
            'accent_color'      => $this->nullify($request->input('accent_color')),
            'support_email'     => $this->nullify($request->input('support_email')),
            'support_phone'     => $this->nullify($request->input('support_phone')),
            'terms_url'         => $this->nullify($request->input('terms_url')),
            'privacy_url'       => $this->nullify($request->input('privacy_url')),
            'footer_text'       => $this->nullify($request->input('footer_text')),
            'email_signature'   => $this->nullify($request->input('email_signature')),
            'mail_from_address' => $this->nullify($request->input('mail_from_address')),
            'mail_from_name'    => $this->nullify($request->input('mail_from_name')),
            'mail_reply_to'     => $this->nullify($request->input('mail_reply_to')),
            'smtp_host'         => $this->nullify($request->input('smtp_host')),
            'smtp_port'         => $request->filled('smtp_port') ? (int) $request->input('smtp_port') : null,
            'smtp_username'     => $this->nullify($request->input('smtp_username')),
            'smtp_encryption'   => $this->nullify($request->input('smtp_encryption')) ?: 'tls',
        ]);

        // Password field handling — empty = preserve existing (so the
        // coach can edit other fields without re-typing their password).
        // Explicit "Clear SMTP" checkbox wipes EVERYTHING SMTP including
        // the password.
        if ($request->boolean('clear_smtp')) {
            $row->smtp_host = null;
            $row->smtp_port = null;
            $row->smtp_username = null;
            $row->smtp_password_encrypted = null;
            $row->smtp_verified_at = null;
        } elseif ($request->filled('smtp_password')) {
            $row->smtp_password_encrypted = $request->input('smtp_password');
        }

        // Verify SMTP on save so ONLY working credentials are ever used for
        // sending. Broken/unverified SMTP -> smtp_verified_at stays null and
        // the notification pipeline falls back to the platform mailer, so a
        // coach's emails never silently fail because of a bad SMTP entry.
        if (! $request->boolean('clear_smtp')
            && $row->smtp_host && $row->smtp_username && $row->smtp_password_encrypted) {
            try {
                $res = app(CoachMailer::class)->verifyCredentials([
                    'smtp_host'         => $row->smtp_host,
                    'smtp_port'         => $row->smtp_port,
                    'smtp_username'     => $row->smtp_username,
                    'smtp_password'     => $row->smtp_password_encrypted, // decrypted by cast
                    'smtp_encryption'   => $row->smtp_encryption,
                    'mail_from_address' => $row->mail_from_address,
                    'mail_from_name'    => $row->mail_from_name,
                ], (string) (userAuth()->email ?? ''));
                $row->smtp_verified_at = !empty($res['ok']) ? now() : null;
            } catch (\Throwable $e) {
                $row->smtp_verified_at = null;
            }
        } else {
            $row->smtp_verified_at = null;
        }

        $row->save();

        return redirect()
            ->route('instructor.brand-settings.edit')
            ->with([
                'messege'    => __('Brand settings saved'),
                'alert-type' => 'success',
            ]);
    }

    /**
     * Verify a set of SMTP credentials WITHOUT persisting them.
     * Coach clicks "Test SMTP" → JS POSTs the current form values
     * → we attempt a one-shot send to the coach's own email →
     * return ok/error JSON for the UI to display.
     */
    public function testSmtp(Request $request, CoachMailer $mailer): JsonResponse
    {
        abort_unless(userAuth()?->role === 'instructor', 403);

        $request->validate([
            'smtp_host'         => ['required', 'string', 'max:200'],
            'smtp_port'         => ['required', 'integer', 'min:1', 'max:65535'],
            'smtp_username'     => ['required', 'string', 'max:200'],
            'smtp_password'     => ['required', 'string', 'max:200'],
            'smtp_encryption'   => ['nullable', 'in:none,ssl,tls'],
            'mail_from_address' => ['nullable', 'email', 'max:120'],
            'mail_from_name'    => ['nullable', 'string', 'max:120'],
        ]);

        $coach = userAuth();
        $result = $mailer->verifyCredentials($request->only([
            'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password',
            'smtp_encryption', 'mail_from_address', 'mail_from_name',
        ]), (string) $coach->email);

        return response()->json($result);
    }

    /**
     * Move uploaded file into public/uploads/coach-brand/ with a
     * stable, coach-scoped filename. Returns the relative path
     * stored in the DB.
     */
    protected function store(\Illuminate\Http\UploadedFile $file, int $coachId, string $kind): string
    {
        $dir = public_path($this->uploadDir);
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        // 2026-06-25 (security, FT-UPLOAD-2) — derive the extension from the
        // SERVER-detected MIME, never the client filename, and clamp to an image
        // allowlist. Pre-fix, a polyglot (valid image bytes + PHP) uploaded as
        // "x.phtml" passed the image/mimes validation and was then written into
        // the public web root with its client `.phtml` extension → executable RCE.
        $ext = strtolower($file->extension() ?: 'png');
        if (! in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'ico'], true)) {
            $ext = 'png';
        }
        $name = "coach-{$coachId}-{$kind}-" . Str::random(8) . '.' . $ext;
        $file->move($dir, $name);

        return $this->uploadDir . '/' . $name;
    }

    /**
     * Delete a previously-stored coach asset. Tolerant of missing files
     * (a manual filesystem cleanup shouldn't error the save flow).
     */
    protected function deletePrevious(?string $relative): void
    {
        if (! $relative) return;
        $abs = public_path($relative);
        try {
            if (File::exists($abs)) File::delete($abs);
        } catch (\Throwable $e) {
            // Best-effort — log if it matters, don't break the save.
        }
    }

    protected function nullify(mixed $v): ?string
    {
        if ($v === null) return null;
        $s = trim((string) $v);
        return $s === '' ? null : $s;
    }
}
