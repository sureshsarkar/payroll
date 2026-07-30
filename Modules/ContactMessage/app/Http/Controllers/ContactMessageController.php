<?php

namespace Modules\ContactMessage\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Rules\CustomRecaptcha;
use Cache;
use Illuminate\Http\Request;
use Modules\ContactMessage\app\Jobs\ContactMessageSendJob;
use Modules\ContactMessage\app\Models\ContactMessage;

class ContactMessageController extends Controller
{
    public function store(Request $request)
    {
        $setting = Cache::get('setting');

        // FT-VAL-1 fix (2026-05-27) — added `email` / `string` / `max`
        // rules. The original `'email' => 'required'` accepted any
        // string, which then got persisted into contact_messages.email
        // and emailed back to the admin notification recipient. That
        // both polluted the inbox and (because the column is bound to
        // a mail "to" address downstream) opened a small fan-out vector
        // when bounced. Length caps keep DB columns from being abused.
        $request->validate([
            'name'    => 'required|string|max:190',
            'email'   => 'required|email|max:190',
            'subject' => 'required|string|max:190',
            'message' => 'required|string|max:5000',
            'g-recaptcha-response' => $setting->recaptcha_status == 'active' ? ['required', new CustomRecaptcha()] : '',
        ], [
            'name.required'    => __('Name is required'),
            'email.required'   => __('Email is required'),
            'email.email'      => __('Please enter a valid email address'),
            'subject.required' => __('Subject is required'),
            'message.required' => __('Message is required'),
            'g-recaptcha-response.required' => __('Please complete the recaptcha to submit the form'),
        ]);

        $new_message = new ContactMessage();
        $new_message->name = $request->name;
        $new_message->email = $request->email;
        $new_message->subject = $request->subject;
        $new_message->message = $request->message;
        $new_message->phone = $request->phone;
        $new_message->save();

        dispatch(new ContactMessageSendJob($new_message));

        return response()->json(['message' => __('Message sent successfully')]);
    }
}
