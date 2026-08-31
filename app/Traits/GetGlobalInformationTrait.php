<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

/**
 * LMS removal phase 2 (2026-08-27) — this trait used to be mostly payment
 * plumbing for the course checkout: get_basic_payment_info() and
 * get_payment_gateway_info() (BasicPayment module), getMultiCurrencyInfo() /
 * getCurrencyDetails(), and calculate_payable_charge(), which converted a
 * course price into the buyer's currency and applied the gateway fee. Nothing
 * is sold any more and the BasicPayment module is deleted.
 *
 * What is left is set_mail_config(), which every auth mail path (registration
 * verify, password reset, social-login default password) relies on — those are
 * the only consumers of this trait that survive.
 */
trait GetGlobalInformationTrait
{
    // mail configuraton setup
    private function set_mail_config()
    {
        $email_setting = Cache::get('setting');
        $mailConfig = [
            'transport'  => 'smtp',
            'host'       => $email_setting->mail_host,
            'port'       => $email_setting->mail_port,
            'encryption' => $email_setting->mail_encryption,
            'username'   => $email_setting->mail_username,
            'password'   => $email_setting->mail_password,
            'timeout'    => null,
        ];

        config(['mail.mailers.smtp' => $mailConfig]);
        config(['mail.from.address' => $email_setting->mail_sender_email]);
        config(['mail.from.name' => $email_setting->mail_sender_name]);
    }
}
