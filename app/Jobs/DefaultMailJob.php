<?php

namespace App\Jobs;

use App\Mail\DefaultMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * FT-NOTIF-1 fix (2026-05-27) — DefaultMailJob constructor arg mismatch.
 *
 * Background: all 7 caller sites in the codebase dispatch this job with
 * three positional arguments:
 *
 *     DefaultMailJob::dispatch($mailData['email'], $mailData, $message);
 *
 * But the constructor originally took only two ($mailData, $messageTemplate).
 * Laravel's pending-dispatch then bound:
 *
 *     $this->mailData         = "santosh@example.com"   (string!)
 *     $this->messageTemplate  = ['email'=>'…', …]       (the actual array)
 *
 * Inside handle(), `Mail::to($this->mailData['email'])` then tried to
 * read offset 'email' from the email STRING (not the array) and threw
 * silently inside the queue worker — every queued order-confirmation /
 * payment-status / gift-claim / newsletter email since queues were
 * enabled has been failing.
 *
 * The fix accepts THREE args matching the existing 7 call sites:
 *
 *     DefaultMailJob::dispatch($recipient, $mailData, $messageTemplate)
 *
 * `$recipient` may be a string email, a User model (or anything with an
 * `email` attribute), or omitted — when omitted, falls back to
 * `$mailData['email']` for backward compat with any future caller that
 * only passes 2 args.
 *
 * Call sites that need to be aware:
 *   - app/Http/Controllers/Frontend/HomePageController.php:256
 *   - Modules/BasicPayment/app/Http/Controllers/PaymentController.php:1176, 1201
 *   - Modules/BasicPayment/app/Http/Controllers/API/PaymentController.php:865, 888
 *   - Modules/Subscription/app/Traits/GiftOrderTraits.php:66
 *   - Modules/Order/app/Traits/GiftOrderTraits.php:66
 *
 * No call-site changes required — the constructor now matches the
 * existing 3-arg call pattern.
 */
class DefaultMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Recipient email or model. May be string|object|null. */
    public $recipient;

    /** The mail-data array (subject, email, name, etc.). */
    public $mailData;

    /** Optional template / rendered message string. */
    public $subject;
    public $messageTemplate;

    public function __construct($recipient, $mailData = null, $messageTemplate = null)
    {
        // Backward-compat path — original 2-arg signature was
        //   DefaultMailJob($mailData_array, $messageTemplate_string).
        // Detect that case: first arg is an array and second arg is
        // either omitted, null, or a string (which would be the
        // messageTemplate, not a mailData array).
        if (is_array($recipient) && ($mailData === null || is_string($mailData))) {
            // Old 2-arg call:  $recipient is actually $mailData;
            //                  $mailData is actually $messageTemplate.
            $this->mailData        = $recipient;
            $this->messageTemplate = $mailData;
            $this->recipient       = $recipient['email'] ?? null;
        } else {
            // New 3-arg call:  $recipient + $mailData + $messageTemplate
            $this->recipient       = $recipient;
            $this->mailData        = is_array($mailData) ? $mailData : [];
            $this->messageTemplate = $messageTemplate;
        }

        $this->subject = $this->mailData['subject'] ?? '';
    }

    public function handle(): void
    {
        // Resolve the actual recipient address. Prefer the explicit
        // $recipient ctor arg; fall back to $mailData['email'] for
        // robustness even if a caller forgets the first arg.
        $to = is_string($this->recipient) && $this->recipient !== ''
            ? $this->recipient
            : ($this->mailData['email'] ?? null);

        if (! $to) {
            // Don't throw inside the queue worker (would put the job in
            // the failed_jobs table for ops to babysit). Log + return.
            Log::warning('DefaultMailJob: no recipient address resolved', [
                'mailData_keys' => array_keys($this->mailData),
            ]);
            return;
        }

        Mail::to($to)->send(new DefaultMail($this->mailData, $this->messageTemplate));
    }
}
