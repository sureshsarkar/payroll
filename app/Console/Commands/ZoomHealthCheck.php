<?php

namespace App\Console\Commands;

use App\Models\ZoomCredential;
use App\Notifications\ZoomReconnectRequired;
use App\Services\ZoomApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Daily health probe for every instructor's Zoom Server-to-Server OAuth
 * credential. Rewritten 2026-05-08 from the legacy User-OAuth flow.
 *
 * Why this exists: with User OAuth, instructor 1079's refresh_token died
 * on 2026-04-07 and stayed dead until 2026-05-07 — a full month of stuck
 * "Joining Meeting…" with no operational visibility. We migrated to S2S
 * OAuth, which has no refresh token (so no "dead refresh token" failure
 * mode) — but credentials can still go bad if the operator regenerates
 * the secret on Zoom's side, mistypes the account_id, or has scopes
 * removed by the Zoom admin. This command catches those.
 *
 * Status values:
 *   ok        — token endpoint returned 200 with an access_token
 *   dead      — token endpoint rejected (401 invalid_client / 400
 *               invalid_request) or response was malformed
 *   unknown   — credentials not yet configured or network failure
 *
 * 'expiring' is gone — S2S tokens have no rotation calendar.
 *
 * On state change to 'dead' we notify the instructor once. We don't
 * re-notify each day for the same status — only when the status flips.
 */
class ZoomHealthCheck extends Command
{
    protected $signature = 'zoom:health-check
        {--instructor= : Only check the given instructor ID (default: all)}
        {--quiet-on-ok : Suppress per-credential output for healthy rows}';

    protected $description = 'Probe each instructor\'s Zoom S2S OAuth credential and update health status.';

    public function handle(): int
    {
        $query = ZoomCredential::query();
        if ($id = $this->option('instructor')) {
            $query->where('instructor_id', $id);
        }

        $credentials = $query->get();
        if ($credentials->isEmpty()) {
            $this->info('No zoom_credentials rows to check.');
            return self::SUCCESS;
        }

        $tally = ['ok' => 0, 'dead' => 0, 'unknown' => 0];

        foreach ($credentials as $cred) {
            $previous = (string) ($cred->health_status ?? 'unknown');
            [$status, $message] = $this->probe($cred);
            $cred->forceFill([
                'health_status'        => $status,
                'health_message'       => $message,
                'last_health_check_at' => now(),
            ])->save();

            $tally[$status] = ($tally[$status] ?? 0) + 1;

            if (!$this->option('quiet-on-ok') || $status !== 'ok') {
                $line = sprintf(
                    '  [%s] instructor=%d %s',
                    strtoupper($status),
                    $cred->instructor_id,
                    $message
                );
                $this->{$status === 'ok' ? 'info' : ($status === 'dead' ? 'error' : 'warn')}($line);
            }

            // Only notify on a *change* to dead — daily nags would
            // train instructors to ignore the email.
            if ($status === 'dead' && $status !== $previous) {
                $this->notifyInstructor($cred, $status, $message);
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Summary: %d ok, %d dead, %d unknown',
            $tally['ok'], $tally['dead'], $tally['unknown']
        ));

        return $tally['dead'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{0: 'ok'|'dead'|'unknown', 1: string}
     */
    private function probe(ZoomCredential $cred): array
    {
        if (!$cred->account_id || !$cred->client_id || !$cred->client_secret) {
            return ['unknown', 'credential missing account_id, client_id, or client_secret'];
        }

        try {
            $response = Http::withBasicAuth($cred->client_id, $cred->client_secret)
                ->asForm()
                ->timeout(15)
                ->post('https://zoom.us/oauth/token', [
                    'grant_type' => 'account_credentials',
                    'account_id' => $cred->account_id,
                ]);
        } catch (\Throwable $e) {
            return ['unknown', 'network error: ' . substr($e->getMessage(), 0, 180)];
        }

        if (!$response->successful()) {
            $body = $response->json();
            $err  = is_array($body) ? ($body['error'] ?? $body['message'] ?? $response->status()) : $response->status();
            return ['dead', sprintf('HTTP %d %s', $response->status(), (string) $err)];
        }

        $data = $response->json();
        if (!isset($data['access_token'])) {
            return ['dead', 'malformed token response (no access_token)'];
        }

        // Cache the freshly-minted token so the next live-class create call
        // doesn't have to hit the token endpoint again. Same shape as
        // ZoomApiService::ensureFreshAccessToken.
        $cred->forceFill([
            'zoom_access_token'     => $data['access_token'],
            'zoom_token_expires_at' => now()->addSeconds((int) ($data['expires_in'] ?? 3600)),
        ])->save();

        return ['ok', 'token minted'];
    }

    private function notifyInstructor(ZoomCredential $cred, string $status, string $message): void
    {
        $user = $cred->instructor;
        if (!$user || !$user->email) {
            $this->warn(sprintf('  ↳ no instructor or email for credential %d, skipping notification', $cred->id));
            return;
        }
        try {
            $user->notify(new ZoomReconnectRequired($status, $message));
        } catch (\Throwable $e) {
            $this->error('  ↳ notification failed: ' . $e->getMessage());
        }
    }
}
