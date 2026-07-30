<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Jobs\DefaultMailJob;
use App\Mail\DefaultMail;
use App\Rules\CustomRecaptcha;
use App\Traits\MailSenderTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Modules\ContactMessage\app\Emails\ContactMessageMail;
use Modules\ContactMessage\app\Jobs\ContactMessageSendJob;
use Modules\ContactMessage\app\Models\ContactMessage;
use Modules\Frontend\app\Models\ContactSection;
use Modules\GlobalSetting\app\Models\EmailTemplate;

class ContactController extends Controller
{
    use MailSenderTrait;

    function index()
    {
        $contact = ContactSection::first();
        return view('frontend.pages.contact', compact('contact'));
    }

    function sendMail(Request $request)
    {

        $validated = $request->validate([
            'fname' => ['required', 'string', 'max:255'],
            'lname' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'numeric'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:1000'],
            'g-recaptcha-response' => Cache::get('setting')?->recaptcha_status == 'active' ? ['required', new CustomRecaptcha()] : 'nullable',

        ], [
            'fname.required' => __('Name is required'),
            'email.required' => __('Email is required'),
            // 'subject.required' => __('Subject is required'),
            'message.required' => __('Message is required'),
            'fname.string' => __('Name must be a string'),
            'lname.string' => __('Last Name must be a string'),
            'email.string' => __('Email must be a string'),
            'phone.required' => __('Phone is required.'),
            'phone.string' => __('Phone must be string.'),
            'phone.numberic' => __('Phone must be numberic.'),
            // 'phone.max'  => __('Phone should be maximum 14 digits.'),
            // 'phone.min' => __('Phone should be minimum of 10 digits.'),
            // 'subject.string' => __('Subject must be a string'),
            'message.string' => __('Message must be a string'),
            'fname.max' => __('Name may not be greater than 255 characters'),
            'lname.max' => __('Last Name may not be greater than 255 characters'),
            'email.max' => __('Email may not be greater than 255 characters'),
            // 'subject.max' => __('Subject may not be greater than 255 characters'),
            'message.max' => __('Message may not be greater than 1000 characters'),
            // 'g-recaptcha-response.required' => __('Please complete the recaptcha to submit the form'),
        ]);

        $name = $request->fname . ' ' . $request->lname;

        $contact = new ContactMessage();
        $contact->name = $request->fname . ' ' . $request->lname;
        $contact->email = $request->email;
        $contact->subject = $request->subject;
        $contact->message = $request->message;
        $contact->save();

        $settings = cache()->get('setting');
        $marketingSettings = cache()->get('marketing_setting');
        if ($settings->google_tagmanager_status == 'active' && $marketingSettings->contact_page) {
            $contactUs = [
                'name' => $contact->name,
                'email' => $contact->email,
                'subject' => $contact->subject,
                'message' => $contact->message,
            ];
            session()->put('contactUs', $contactUs);
        }

        self::setMailConfig();

        $template = EmailTemplate::where('name', 'contact_mail')->first();

        // Header-injection guard (CRLF) + body XSS escape — see ContactMessageSendJob
        // for the same defenses on the queued path.
        $stripCrlf = fn ($v) => preg_replace('/[\r\n]+/', ' ', (string) $v);
        $esc       = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $subject = $stripCrlf($template->subject);
        $message = $template->message;
        $message = str_replace('{{name}}',    $esc($stripCrlf($name)), $message);
        $message = str_replace('{{email}}',   $esc($stripCrlf($request->email)), $message);
        $message = str_replace('{{phone}}',   $esc($stripCrlf($request->phone)), $message);
        $message = str_replace('{{subject}}', $esc($stripCrlf($request->subject)), $message);
        $message = str_replace('{{message}}', nl2br($esc($request->message)), $message);

        $email_setting = Cache::get('setting');

        if (self::isQueable()) {
            ContactMessageSendJob::dispatch(collect($validated));
        } else {
            Mail::to($email_setting->contact_message_receiver_mail)->send(new ContactMessageMail($subject, $message));
        }
        return response()->json(['success' => true, 'message' => 'message sent successfully'], 200);
    }
}
