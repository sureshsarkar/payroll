<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachEmailTemplate;
use App\Notifications\NotificationEmailTemplates;
use Illuminate\Http\Request;
use Modules\GlobalSetting\app\Models\EmailTemplate;

/**
 * 2026-06-26 — per-coach email template editor. A coach customises the subject/
 * body of their white-label notification emails; when they haven't, the
 * platform default is used (resolved in InAppNotification::toMail). Tenant-safe:
 * every read/write is scoped to the logged-in coach's own id — a coach can never
 * see or edit another coach's overrides.
 */
class CoachEmailTemplateController extends Controller
{
    private function coachId(): ?int
    {
        $u = userAuth();
        if (! $u) {
            return null;
        }
        return $u->role === 'instructor' ? (int) $u->id : ($u->coach_id ? (int) $u->coach_id : null);
    }

    /** Platform default subject/body for a key (the fallback the coach overrides). */
    private function platformDefault(string $key): array
    {
        $row = EmailTemplate::where('name', $key)->first();
        return [
            'subject' => (string) ($row->subject ?? ''),
            'message' => (string) ($row->message ?? ''),
        ];
    }

    public function index()
    {
        $coachId = $this->coachId();
        $editable = NotificationEmailTemplates::coachEditable();

        $overridden = $coachId
            ? CoachEmailTemplate::forCoach($coachId)->pluck('name')->all()
            : [];

        $rows = [];
        foreach ($editable as $key => $meta) {
            $rows[$key] = [
                'key'          => $key,
                'label'        => $meta['label'] ?? $key,
                'is_custom'    => in_array($key, $overridden, true),
            ];
        }

        return view('frontend.instructor-dashboard.email-templates.index', ['rows' => $rows]);
    }

    public function edit(string $key)
    {
        $editable = NotificationEmailTemplates::coachEditable();
        abort_unless(isset($editable[$key]), 404);

        $coachId = $this->coachId();
        $override = CoachEmailTemplate::override($coachId, $key);
        $default  = $this->platformDefault($key);

        return view('frontend.instructor-dashboard.email-templates.edit', [
            'key'          => $key,
            'meta'         => $editable[$key],
            'subject'      => $override->subject ?? $default['subject'],
            'message'      => $override->message ?? $default['message'],
            'is_custom'    => (bool) $override,
            'default'      => $default,
            'placeholders' => $editable[$key]['placeholders'] ?? [],
        ]);
    }

    public function update(Request $request, string $key)
    {
        $editable = NotificationEmailTemplates::coachEditable();
        abort_unless(isset($editable[$key]), 404);

        $coachId = $this->coachId();
        abort_unless($coachId, 403);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:50000'],
        ]);

        CoachEmailTemplate::updateOrCreate(
            ['coach_id' => $coachId, 'name' => $key],
            ['subject' => $data['subject'], 'message' => $data['message']]
        );

        return redirect()
            ->route('instructor.email-templates.index')
            ->with(['messege' => __('Email template saved.'), 'alert-type' => 'success']);
    }

    /** Remove the coach override → revert to the platform default. */
    public function reset(string $key)
    {
        $coachId = $this->coachId();
        if ($coachId) {
            CoachEmailTemplate::forCoach($coachId)->where('name', $key)->delete();
        }

        return redirect()
            ->route('instructor.email-templates.index')
            ->with(['messege' => __('Reverted to the default template.'), 'alert-type' => 'success']);
    }
}
