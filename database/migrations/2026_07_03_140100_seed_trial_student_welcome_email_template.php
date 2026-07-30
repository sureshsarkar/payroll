<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Coach-editable welcome email (with login credentials) for a student account
 * auto-created after a trial payment (2026-07-03). `notif_` prefixed so coaches
 * can override it. Idempotent (email_templates.name is NOT unique).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        $name    = 'notif_trial_student_welcome';
        $subject = 'Welcome to {{organization_name}} — your account & trial are ready';
        $body    = <<<HTML
<p>Hi {{student_name}},</p>
<p>Welcome to <strong>{{organization_name}}</strong>! Your student account has been created and your trial session is confirmed.</p>
<p><strong>Your login details</strong></p>
<table style="border-collapse:collapse;font-size:14px;">
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Login URL</td><td style="padding:4px 0;"><a href="{{login_url}}">{{login_url}}</a></td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Email</td><td style="padding:4px 0;">{{student_email}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Temporary password</td><td style="padding:4px 0;"><code>{{temp_password}}</code></td></tr>
</table>
<p style="color:#b45309;">For your security, please change this password after your first login.</p>
<p><strong>Trial session</strong></p>
<table style="border-collapse:collapse;font-size:14px;">
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Time slot</td><td style="padding:4px 0;">{{time_slot}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Amount</td><td style="padding:4px 0;">{{amount}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Payment</td><td style="padding:4px 0;">{{payment_status}}</td></tr>
</table>
<p>Need help? Contact us at {{support_email}}.</p>
HTML;

        DB::table('email_templates')->where('name', $name)->update([
            'subject' => $subject, 'message' => $body, 'updated_at' => now(),
        ]);
        if (! DB::table('email_templates')->where('name', $name)->exists()) {
            DB::table('email_templates')->insert([
                'name' => $name, 'subject' => $subject, 'message' => $body,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('email_templates')) {
            DB::table('email_templates')->where('name', 'notif_trial_student_welcome')->delete();
        }
    }
};
