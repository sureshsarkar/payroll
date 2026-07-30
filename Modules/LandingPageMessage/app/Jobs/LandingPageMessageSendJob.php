<?php

namespace Modules\LandingPageMessage\app\Jobs;

use App\Traits\GetGlobalInformationTrait;
use Cache;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Mail;
use Modules\LandingPageMessage\app\Emails\LandingPageMessageMail;
use Modules\GlobalSetting\app\Models\EmailTemplate;

class LandingPageMessageSendJob implements ShouldQueue
{
    use Dispatchable, GetGlobalInformationTrait, InteractsWithQueue, Queueable, SerializesModels;

    private $contact_message;

    public function __construct($contact_message)
    {
        $this->contact_message = $contact_message;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->set_mail_config();

        try {
            $template = EmailTemplate::where('name', 'landing_mail')->first();
            $subject = $template->subject;
            $message = $template->message;
            $message = str_replace('{{name}}', $this->contact_message->first_name.' '.$this->contact_message->last_name, $message);
            $message = str_replace('{{email}}', $this->contact_message->email, $message);
            $message = str_replace('{{phone}}', $this->contact_message->phone, $message);
            $message = str_replace('{{service}}', $this->contact_message->service, $message);
            $message = str_replace('{{message}}', $this->contact_message->message, $message);

            $email_setting = Cache::get('setting');

            Mail::to($email_setting->contact_message_receiver_mail)->send(new LandingPageMessageMail($subject, $message));
        } catch (Exception $ex) {
        }
    }
}
