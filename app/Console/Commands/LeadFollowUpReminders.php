<?php

namespace App\Console\Commands;

use App\Models\LandingPageEnquiry;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * 2026-06-22 — CRM: notify the responsible staff (or the coach) when a lead's
 * scheduled follow-up time has arrived. Coach-branded + tenant-safe (the
 * notification resolves the coach from the enquiry). Deduped via
 * follow_up_reminded_at so each scheduled follow-up reminds once.
 */
class LeadFollowUpReminders extends Command
{
    protected $signature = 'leads:follow-up-reminders';
    protected $description = 'Remind staff/coach about leads whose follow-up time is due';

    public function handle(): int
    {
        $closed = [
            LandingPageEnquiry::STATUS_WON,
            LandingPageEnquiry::STATUS_LOST,
            LandingPageEnquiry::STATUS_SPAM,
        ];

        LandingPageEnquiry::query()
            ->whereNotNull('follow_up_at')
            ->where('follow_up_at', '<=', now())
            ->whereNotIn('status', $closed)
            ->where(function ($q) {
                $q->whereNull('follow_up_reminded_at')
                  ->orWhereColumn('follow_up_reminded_at', '<', 'follow_up_at');
            })
            ->chunkById(200, function ($enquiries) {
                foreach ($enquiries as $enquiry) {
                    // Responsible person: assigned staff, else the owning coach.
                    $recipientId = $enquiry->assigned_to ?: $enquiry->coach_id;
                    $recipient = $recipientId ? User::find($recipientId) : null;

                    if ($recipient) {
                        try {
                            $recipient->notify(new \App\Notifications\LeadFollowUpReminderToStaff($enquiry));
                        } catch (\Throwable $e) {
                            \Log::warning('Lead follow-up reminder failed: ' . $e->getMessage());
                        }
                    }

                    // Stamp regardless so a missing recipient doesn't loop forever.
                    $enquiry->forceFill(['follow_up_reminded_at' => now()])->saveQuietly();
                }
            });

        return self::SUCCESS;
    }
}
