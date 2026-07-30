<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit 2026-06-22 — seed the three notification email_templates rows that
 * were referenced in code but never seeded, so a fresh DB rendered them with
 * the generic InAppNotification fallback (plain subject/body):
 *   - notif_fee_demand_published  (FeeDemandPublishedToStudent)
 *   - notif_fee_payment_receipt   (FeePaymentReceiptToStudent)
 *   - notif_new_landing_lead      (NewLandingPageEnquiryToCoach)
 *
 * Idempotent — updateOrInsert keyed on `name`. Placeholders match each
 * notification's placeholders() map exactly. The email itself is rendered
 * (coach-branded) by the BrandedNotificationMail pipeline; this row only
 * supplies the editable subject + body.
 */
return new class extends Migration
{
    private array $templates = [];

    public function __construct()
    {
        $this->templates = [
            [
                'name'    => 'notif_fee_demand_published',
                'subject' => 'New fee due — {{fee_title}}',
                'message' => <<<HTML
<p>Hi {{user_name}},</p>

<p>A new fee has been published for your batch <strong>{{batch_title}}</strong>:</p>

<p><strong>{{fee_title}}</strong> — {{amount}}<br>
Due: {{due_date}}</p>

<p>Please complete the payment from your fees page before the due date.</p>
HTML,
            ],
            [
                'name'    => 'notif_fee_payment_receipt',
                'subject' => 'Payment received — receipt {{receipt_no}}',
                'message' => <<<HTML
<p>Hi {{user_name}},</p>

<p>We have received your payment. Here is your receipt:</p>

<p><strong>Amount:</strong> {{amount}}<br>
<strong>For:</strong> {{fee_title}}<br>
<strong>Receipt no:</strong> {{receipt_no}}<br>
<strong>Paid on:</strong> {{paid_at}}<br>
<strong>Method:</strong> {{gateway}}</p>

<p>Thank you. You can view all your fees and receipts from your dashboard.</p>
HTML,
            ],
            [
                'name'    => 'notif_new_landing_lead',
                'subject' => 'New lead — {{service}}',
                'message' => <<<HTML
<p>Hi {{coach_name}},</p>

<p>You received a new enquiry from your website{{vertical}}:</p>

<p><strong>{{lead_name}}</strong><br>
{{lead_email}}<br>
{{lead_phone}}</p>

<p><strong>Interested in:</strong> {{service}}</p>

<blockquote style="border-left:4px solid #5751e1; padding:8px 12px; margin:12px 0; color:#333;">
{{message}}
</blockquote>

<p>Open your leads dashboard to follow up.</p>
HTML,
            ],
        ];
    }

    public function up(): void
    {
        if (!Schema::hasTable('email_templates')) {
            return;
        }

        foreach ($this->templates as $t) {
            DB::table('email_templates')->updateOrInsert(
                ['name' => $t['name']],
                [
                    'subject'    => $t['subject'],
                    'message'    => $t['message'],
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('email_templates')) {
            return;
        }
        DB::table('email_templates')
            ->whereIn('name', array_column($this->templates, 'name'))
            ->delete();
    }
};
