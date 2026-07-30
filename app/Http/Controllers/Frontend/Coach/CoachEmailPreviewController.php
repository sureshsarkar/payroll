<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use App\Services\BrandResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * 2026-06-22 — Coach email preview + test-send. Lets a coach see how their
 * white-label emails look (rendered in THEIR brand) and send themselves a live
 * test. Tenant-safe: always resolves the logged-in coach's own brand; a coach
 * can never preview/test as another coach.
 */
class CoachEmailPreviewController extends Controller
{
    /** Representative sample emails the coach can preview. */
    private function samples(): array
    {
        return [
            'live_class' => [
                'label' => 'Live class scheduled',
                'title' => 'New live class scheduled',
                'body'  => '"Class 11 Physics" — Rotational Motion (Morning Batch) on 22 Jun 2026, 7:00 AM',
                'icon'  => 'fa-video', 'iconColor' => '#3b82f6', 'cta' => 'Open live classes',
            ],
            'payment' => [
                'label' => 'Payment received',
                'title' => 'Payment received',
                'body'  => 'Receipt ACM-000124 · Term 1 fees · 5,000',
                'icon'  => 'fa-receipt', 'iconColor' => '#10b981', 'cta' => 'Open my fees',
            ],
            'announcement' => [
                'label' => 'Batch announcement',
                'title' => 'New announcement — Morning Batch',
                'body'  => 'Holiday on Friday — class rescheduled to Saturday 8 AM.',
                'icon'  => 'fa-bullhorn', 'iconColor' => '#f59e0b', 'cta' => 'View announcement',
            ],
            'lead' => [
                'label' => 'New lead',
                'title' => 'New lead from your landing page',
                'body'  => 'Riya Sharma is interested in "NEET Coaching".',
                'icon'  => 'fa-bullseye', 'iconColor' => '#0d9488', 'cta' => 'Open lead',
            ],
        ];
    }

    private function currentCoachId(): ?int
    {
        $u = userAuth();
        if (!$u) {
            return null;
        }
        return $u->role === 'instructor' ? (int) $u->id : ($u->coach_id ? (int) $u->coach_id : null);
    }

    /** Build the branded view-data for one sample, in the coach's brand. */
    private function viewData(string $key): array
    {
        $samples = $this->samples();
        $s = $samples[$key] ?? $samples['live_class'];

        $coachId = $this->currentCoachId();
        $brand = $coachId ? app(BrandResolver::class)->forCoach($coachId) : null;

        return [
            'title'          => $s['title'],
            'body'           => $s['body'],
            'bodyHtml'       => null,
            'url'            => '#',
            'icon'           => $s['icon'],
            'iconColor'      => $s['iconColor'],
            'recipientName'  => userAuth()->name ?? '',
            'ctaLabel'       => $s['cta'],
            'preferencesUrl' => null,
            'appName'        => $brand?->name ?? config('app.name'),
            'appLogo'        => $brand ? ($brand->ownLogo ? $brand->logoUrl() : null) : null,
            'brandColor'     => $brand?->primaryColor ?? '#10b981',  // 2026-07-07: platform default emerald, not legacy indigo (matches BrandedNotificationMail)
            'supportEmail'   => $brand?->supportEmail,
            'supportPhone'   => $brand?->supportPhone,
            'footerText'     => $brand?->footerText,
            'emailSignature' => $brand?->emailSignature,
        ];
    }

    public function index()
    {
        return view('frontend.instructor-dashboard.email-preview.index', [
            'samples' => $this->samples(),
        ]);
    }

    /** Returns the rendered branded email HTML (for the preview iframe). */
    public function render(Request $request)
    {
        $key = (string) $request->query('template', 'live_class');
        $html = view('emails.notification', $this->viewData($key))->render();
        return response($html)->header('Content-Type', 'text/html');
    }

    /** Sends the selected sample to the coach's own email, in their brand. */
    public function testSend(Request $request)
    {
        $request->validate(['template' => ['nullable', 'string']]);
        $key = (string) $request->input('template', 'live_class');

        $to = userAuth()->email ?? null;
        if (!$to) {
            return back()->with(['messege' => __('No email on your account.'), 'alert-type' => 'error']);
        }

        $data = $this->viewData($key);
        $fromName = $data['appName'];

        try {
            // Mailer renders the same branded blade the preview uses.
            Mail::send('emails.notification', $data, function ($m) use ($to, $fromName) {
                $m->to($to)->subject('[Test] ' . __('Your branded email preview'));
                $addr = config('mail.from.address');
                if ($addr) {
                    $m->from($addr, $fromName);
                }
            });
        } catch (\Throwable $e) {
            \Log::warning('Coach email test-send failed: ' . $e->getMessage());
            return back()->with(['messege' => __('Could not send test email. Please try again.'), 'alert-type' => 'error']);
        }

        return back()->with(['messege' => __('Test email sent to') . ' ' . $to, 'alert-type' => 'success']);
    }
}
