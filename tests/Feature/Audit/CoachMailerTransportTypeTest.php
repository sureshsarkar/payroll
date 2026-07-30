<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * Doc-C-SMTP regression pin (2026-05-29).
 *
 * Bug: app/Services/CoachMailer.php was instantiating Illuminate\Mail\Mailer
 * with a Symfony\Component\Mailer\Mailer as the third argument. Laravel's
 * Mailer constructor signature is:
 *
 *   __construct(string $name, Factory $views, TransportInterface $transport, ?Dispatcher $events = null)
 *
 * So the third arg MUST be a TransportInterface (the SMTP / API plug),
 * not a SymfonyMailer wrapper. The bug surfaced as a TypeError on the
 * coach Brand Settings → Test SMTP button.
 *
 * This test asserts the source no longer constructs Illuminate\Mail\Mailer
 * with a SymfonyMailer wrapper anywhere.
 */
class CoachMailerTransportTypeTest extends TestCase
{
    public function test_coach_mailer_does_not_construct_with_symfony_mailer_wrapper(): void
    {
        $src = file_get_contents(base_path('app/Services/CoachMailer.php'));

        // No lingering SymfonyMailer import or usage outside comments.
        $codeLines = collect(explode("\n", $src))
            ->reject(fn ($l) => preg_match('#^\s*//#', $l) || preg_match('#^\s*\*#', $l));

        $sawImport = $codeLines->contains(fn ($l) => str_contains($l, 'Symfony\\Component\\Mailer\\Mailer'));
        $sawUsage  = $codeLines->contains(fn ($l) => preg_match('/\bnew\s+SymfonyMailer\b/', $l));

        $this->assertFalse(
            $sawImport,
            'CoachMailer must NOT import Symfony\\Component\\Mailer\\Mailer (the wrapper).'
        );
        $this->assertFalse(
            $sawUsage,
            'CoachMailer must NOT instantiate SymfonyMailer; pass the EsmtpTransport '
            . 'directly to Illuminate\\Mail\\Mailer\'s constructor.'
        );
    }

    public function test_no_illuminate_mailer_constructor_uses_symfony_wrapper(): void
    {
        $src = file_get_contents(base_path('app/Services/CoachMailer.php'));

        // Capture every `new Mailer(... up to the closing ')' on the
        // same logical line, handling nested parens by walking chars.
        $callTexts = [];
        $pos = 0;
        while (($i = strpos($src, 'new Mailer(', $pos)) !== false) {
            $depth = 0;
            $start = $i + strlen('new Mailer(');
            $j = $start - 1;
            do {
                $j++;
                if ($src[$j] === '(') $depth++;
                elseif ($src[$j] === ')') $depth--;
            } while ($j < strlen($src) - 1 && $depth >= 0);
            $callTexts[] = substr($src, $start, $j - $start);
            $pos = $j + 1;
        }

        $this->assertNotEmpty($callTexts,
            'Expected to find at least one new Mailer(...) instantiation');

        foreach ($callTexts as $blob) {
            // Forbidden: $symfony as any argument (that was the bug).
            $this->assertStringNotContainsString(
                '$symfony',
                $blob,
                'new Mailer(...) must not be passed a SymfonyMailer wrapper. '
                . 'Pass the EsmtpTransport ($transport / $cred) directly.'
            );
        }
    }
}
