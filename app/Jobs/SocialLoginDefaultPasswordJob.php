<?php

namespace App\Jobs;

use App\Mail\SocialLoginDefaultPasswordMail;
use App\Models\User;
use App\Traits\GetGlobalInformationTrait;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SocialLoginDefaultPasswordJob implements ShouldQueue
{
    use Dispatchable, GetGlobalInformationTrait, InteractsWithQueue, Queueable, SerializesModels;

    /** Retry a failed credentials send before giving up (2026-07-09 / Phase 1.2). */
    public $tries = 3;

    public $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(private User $user, private string $password)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->set_mail_config();

        try {
            Mail::to($this->user->email)->send(new SocialLoginDefaultPasswordMail($this->user, $this->password));
        } catch (Exception $ex) {
            // 2026-07-09 fix (Phase 1.2): previously only logged on local and
            // swallowed the error — the credentials email was silently lost in
            // production. Now always logged and rethrown so the queue retries and
            // the failure is recorded in failed_jobs.
            Log::error('Social-login credentials email failed for user '.($this->user->id ?? '?').': '.$ex->getMessage());
            throw $ex;
        }
    }
}
