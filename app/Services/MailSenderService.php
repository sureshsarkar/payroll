<?php

namespace App\Services;

use Exception;
use App\Models\User;
use App\Mail\UserRegistration;
use App\Traits\MailSenderTrait;
use App\Mail\UserForgetPassword;
use App\Jobs\SendVerifyMailToUser;
use App\Jobs\UserForgetPasswordJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Jobs\SocialLoginDefaultPasswordJob;
use App\Mail\SocialLoginDefaultPasswordMail;
use Modules\GlobalSetting\app\Models\EmailTemplate;

class MailSenderService {
    use MailSenderTrait;

    public function sendVerifyMailToUserFromTrait($user_type, $user_info = null) {
        if (self::setMailConfig()) {
            try {
                if (self::isQueable()) {
                    dispatch(new SendVerifyMailToUser($user_type, $user_info));
                } else {
                    if ($user_type == 'all_user') {
                        // Chunk 500 at a time so memory stays bounded — was loading the
                        // entire unverified-users table at once which OOMs at scale.
                        $template = EmailTemplate::where('name', 'user_verification')->first();
                        $subject  = $template?->subject ?? '';
                        $tplMsg   = $template?->message ?? '';
                        User::where('email_verified_at', null)
                            ->orderBy('id', 'desc')
                            ->chunkById(500, function ($users) use ($subject, $tplMsg) {
                                foreach ($users as $user) {
                                    $user->verification_token = \Illuminate\Support\Str::random(100);
                                    $user->save();
                                    try {
                                        $message = str_replace('{{user_name}}', $user->name, $tplMsg);
                                        Mail::to($user->email)->send(new UserRegistration($message, $subject, $user));
                                    } catch (Exception $ex) {
                                        if (app()->isLocal()) {
                                            Log::error($ex->getMessage());
                                        }
                                    }
                                }
                            });
                    } else {
                        try {
                            $template = EmailTemplate::where('name', 'user_verification')->first();
                            $subject = $template->subject;
                            $message = $template->message;
                            
                            $message = str_replace('{{user_name}}', $user_info->name, $message);
                            
                            Mail::to($user_info->email)->send(new UserRegistration($message, $subject, $user_info));
                             
                            
                           

                        } catch (Exception $ex) {
                            if (app()->isLocal()) {
                                Log::error($ex->getMessage());
                            }
                            return false;
                        }
                    }
                }

                return true;
            } catch (Exception $th) {
                if (app()->isLocal()) {
                    Log::error($th->getMessage());
                }

                return false;
            }
        }

        return false;
    }

    public function sendUserForgetPasswordFromTrait($from_user, $mail_template_path = 'auth') {
        if (self::setMailConfig()) {
            try {
                if (self::isQueable()) {
                    dispatch(new UserForgetPasswordJob($from_user, $mail_template_path));
                } else {
                    try {
                        $template = EmailTemplate::where('name', 'password_reset')->first();
                        $subject = $template->subject;
                        $message = $template->message;
                        $message = str_replace('{{user_name}}', $from_user->name, $message);
                        Mail::to($from_user->email)->send(new UserForgetPassword($message, $subject, $from_user, $mail_template_path));
                    } catch (Exception $ex) {
                        if (app()->isLocal()) {
                            Log::error($ex->getMessage());
                        }
                    }
                }

                return true;
            } catch (Exception $th) {
                if (app()->isLocal()) {
                    Log::error($th->getMessage());
                }

                return false;
            }
        }

        return false;
    }

    public function sendSocialLoginDefaultPasswordFromTrait($user, $password) {
        if (self::setMailConfig()) {
            try {
                if (self::isQueable()) {
                    dispatch(new SocialLoginDefaultPasswordJob($user, $password));
                } else {
                    try {
                        Mail::to($user->email)->send(new SocialLoginDefaultPasswordMail($user, $password));
                    } catch (Exception $ex) {
                        if (app()->isLocal()) {
                            Log::error($ex->getMessage());
                        }
                    }
                }

                return true;
            } catch (Exception $th) {
                if (app()->isLocal()) {
                    Log::error($th->getMessage());
                }

                return false;
            }
        }

        return false;
    }

    /* LMS removal phase 2 (2026-08-27) — also removed
     * sendMailToUserFromTrait() and SendUserBannedMailFromTrait(). Both were
     * driven by the Customer module (admin bulk-mail + ban notice) and had no
     * caller outside it. What remains is what auth actually uses: verify-email,
     * forgot-password and the social-login default password. */
}
