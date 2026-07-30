<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class MailTestSmtp extends Command
{
    protected $signature = 'mail:test-smtp {to? : Recipient email (default = MAIL_FROM_ADDRESS)}';

    protected $description = 'Send a test SMTP email to verify MAIL_* config + MAIL_PASSWORD work end-to-end';

    public function handle(): int
    {
        $to = $this->argument('to') ?: config('mail.from.address');
        if (!$to) {
            $this->error('No recipient. Pass an email or set MAIL_FROM_ADDRESS in .env.');
            return self::FAILURE;
        }

        $this->info("Sending via {$to}...");
        $this->line('  host=' . config('mail.mailers.smtp.host'));
        $this->line('  port=' . config('mail.mailers.smtp.port'));
        $this->line('  user=' . config('mail.mailers.smtp.username'));
        $this->line('  encryption=' . config('mail.mailers.smtp.encryption'));

        try {
            Mail::raw('MBS SMTP test — sent ' . now()->toDateTimeString(), function ($m) use ($to) {
                $m->to($to)->subject('MBS SMTP Test');
            });
            $this->info("OK — test mail dispatched to {$to}.");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('FAILED: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
