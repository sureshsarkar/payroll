<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * Regression scanner for email-injection risk.
 *
 * 2026-05-06 audit triaged every Mail::* call in app/ + Modules/.
 * Verdict: zero header-injection or address-spoofing vulnerabilities.
 *
 *   - All Mail::to() recipients pull from DB rows ($user->email,
 *     $email_setting->contact_message_receiver_mail) or validated
 *     form input fields whose Laravel `email` rule rejects \r\n.
 *   - No raw header construction (no setHeader / addCustomHeaders).
 *   - No ->from() overrides — every mailable uses MAIL_FROM_ADDRESS.
 *   - Email body templates wrap user content with clean() (mews/purifier).
 *   - Symfony Mailer (Laravel 10's mail backend) rejects \r\n in
 *     subject / addresses at the Address::fromString() level — defense
 *     in depth even if user input slipped through.
 *
 * Forcing function: any new Mail::to(\$request->X) — sending to a raw
 * request input — gets flagged. The fix in every case is to first
 * validate the address against `email` (rejects newlines) and prefer
 * persisting + rehydrating from the DB.
 */
class EmailInjectionScanTest extends TestCase
{
    /**
     * Patterns considered dangerous. Substring match — these are
     * unmistakably "user input → email recipient/header" smells.
     */
    private const DANGEROUS_PATTERNS = [
        'Mail::to($request->',
        'Mail::to(request()->',
        'Mail::cc($request->',
        'Mail::bcc($request->',
        '->to($request->input',
        '->cc($request->input',
        '->bcc($request->input',
        '->subject($request->input',
        // Custom Symfony header injection
        'addCustomHeaders($request',
        'addTextHeader($request',
    ];

    public function test_no_user_input_flows_into_mail_recipient_or_header(): void
    {
        $hits = [];
        foreach ([base_path('app'), base_path('Modules')] as $root) {
            if (!is_dir($root)) continue;
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iter as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') continue;
                $abs = $file->getPathname();
                if (str_contains($abs, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) continue;
                if (str_contains($abs, DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR)) continue;
                if (basename($abs) === 'AuditSmoke.php') continue;          // pattern strings
                if (basename($abs) === 'EmailInjectionScanTest.php') continue; // self

                $body = (string) file_get_contents($abs);
                foreach (self::DANGEROUS_PATTERNS as $pat) {
                    if (str_contains($body, $pat)) {
                        $rel = str_replace([base_path() . DIRECTORY_SEPARATOR, '\\'], ['', '/'], $abs);
                        $hits[] = "$rel  [$pat]";
                    }
                }
            }
        }
        $this->assertEmpty(
            $hits,
            "User input flowing into Mail::to/cc/bcc/subject. Each must be either:\n" .
            "  (a) validated with the `email` rule first (rejects \\r\\n)\n" .
            "  (b) persisted to the DB and rehydrated as \$model->email\n" .
            "  (c) drawn from server-side admin config (\$setting->X)\n\n" .
            "Sites:\n  " . implode("\n  ", $hits)
        );
    }

    public function test_no_mailable_overrides_from_with_user_input(): void
    {
        // Spoofing the From: header is a phishing primitive. Mailables
        // should leave from() at the default (MAIL_FROM_ADDRESS env).
        $hits = [];
        foreach ([app_path('Mail'), base_path('Modules')] as $root) {
            if (!is_dir($root)) continue;
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iter as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') continue;
                $abs = $file->getPathname();
                if (str_contains($abs, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) continue;
                $body = (string) file_get_contents($abs);
                if (preg_match('/->from\s*\(\s*\$request->/', $body) ||
                    preg_match('/->from\s*\(\s*request\(\)->/', $body)) {
                    $rel = str_replace([base_path() . DIRECTORY_SEPARATOR, '\\'], ['', '/'], $abs);
                    $hits[] = $rel;
                }
            }
        }
        $this->assertEmpty($hits,
            "Mailable ->from() being set from user input — phishing/spoof primitive.\n  " .
            implode("\n  ", $hits)
        );
    }
}
