<?php

namespace Modules\Order\app\Traits;

use App\Jobs\DefaultMailJob;
use App\Mail\DefaultMail;
use App\Models\Course;
use App\Traits\MailSenderTrait;
use Exception;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\GiftCourse\app\Models\GiftCourse;
use Modules\GlobalSetting\app\Models\EmailTemplate;
use Nwidart\Modules\Facades\Module;

trait GiftOrderTraits {
    use MailSenderTrait;
    public function giftOrderDetailsUpdate($order): void {
        $order->order_details = (object) array_merge((array) $order->order_details, [
            "verification_token" => Str::random(100),
        ]);
        $order->save();

        if (Module::has('GiftCourse') && Module::isEnabled('GiftCourse')) {
            $gift_id = $order?->order_details?->gift_id;
            $gift = GiftCourse::whereGiftId($gift_id)->first();
            if ($gift) {
                $gift->delete();
            }
        }
        try {
            $course = Course::find($order?->order_details?->course_id);
            $this->sendingGiftCourseMail([
                'email'        => $order?->order_details?->recipient_email,
                'name'         => $order?->order_details?->recipient_name,
                'sender_name'  => $order?->user?->name,
                'sender_email' => $order?->user?->email,
                'course_link'  => route('course.show', $course->slug),
                'course_name'  => $course->title,
                'message'      => $order?->order_details?->message,
                'link'         => route('gift-course-verification', ['invoice_id' => $order?->invoice_id, 'verification_token' => $order?->order_details?->verification_token]),
            ]);
        } catch (Exception $e) {
            info($e->getMessage());
        }
    }

    public function sendingGiftCourseMail(array $mailData) {
        try {
            self::setMailConfig();

            // Get email template
            $template = EmailTemplate::where('name', 'gift_course')->firstOrFail();
            $mailData['subject'] = $template->subject;

            // Prepare email content
            $message = str_replace('{{name}}', $mailData['name'], $template->message);
            $message = str_replace('{{sender_name}}', $mailData['sender_name'], $message);
            $message = str_replace('{{sender_email}}', $mailData['sender_email'], $message);
            $message = str_replace('{{link}}', $mailData['link'], $message);
            $message = str_replace('{{course_link}}', $mailData['course_link'], $message);
            $message = str_replace('{{course_name}}', $mailData['course_name'], $message);
            $message = str_replace('{{message}}', $mailData['message'], $message);

            if (self::isQueable()) {
                DefaultMailJob::dispatch($mailData['email'], $mailData, $message);
            } else {
                Mail::to($mailData['email'])->send(new DefaultMail($mailData, $message));
            }
        } catch (Exception $e) {
            info($e->getMessage());
        }
    }
}
