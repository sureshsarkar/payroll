<?php

namespace Modules\NewsLetter\app\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Traits\GetGlobalInformationTrait;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\NewsLetter\app\Models\NewsLetter;
use Modules\NewsLetter\app\Emails\SendMailToNewsLetter;

class SendMailToNewsletterJob implements ShouldQueue
{
    use Dispatchable, GetGlobalInformationTrait, InteractsWithQueue, Queueable, SerializesModels;

    private $mail_subject;

    private $mail_template;

    public function __construct($mail_subject, $mail_template)
    {
        $this->mail_subject = $mail_subject;
        $this->mail_template = $mail_template;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->set_mail_config();

        // F52 (audit 2026-06-26) — chunk instead of ->get() on the whole list,
        // and LOG send failures (the catch was previously empty = silent).
        NewsLetter::where('status', 'verified')->orderBy('id')->chunkById(200, function ($newsletters) {
            foreach ($newsletters as $item) {
                try {
                    Mail::to($item->email)->send(new SendMailToNewsLetter($this->mail_subject, $this->mail_template));
                } catch (Exception $ex) {
                    logger($ex);
                }
            }
        });
    }
}
