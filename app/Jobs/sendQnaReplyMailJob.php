<?php

namespace App\Jobs;

use App\Mail\sendQnaReplyMail;
use App\Traits\GetGlobalInformationTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class sendQnaReplyMailJob implements ShouldQueue {
    use Dispatchable, GetGlobalInformationTrait, InteractsWithQueue, Queueable, SerializesModels;

    /** Phase 5.5: retry a failed send + surface it in failed_jobs. */
    public $tries = 3;

    public $backoff = 30;

    public $mail_email;
    public $mail_subject;

    public $mail_message;

    public $link;

    public function __construct($mail_email, $mail_subject, $mail_message, $link) {
        $this->mail_email = $mail_email;
        $this->mail_subject = $mail_subject;
        $this->mail_message = $mail_message;
        $this->link = $link;
    }

    /**
     * Execute the job.
     */
    public function handle(): void {
        $this->set_mail_config();

        try {
            Mail::to($this->mail_email)->send(new sendQnaReplyMail($this->mail_subject, $this->mail_message, $this->link));
        } catch (\Exception $ex) {
            // Phase 5.5: was an empty catch — a failed QnA-reply email vanished
            // with no trail. Log + rethrow so the queue retries and records it.
            Log::error('QnA-reply email failed for ' . $this->mail_email . ': ' . $ex->getMessage());
            throw $ex;
        }
    }
}
