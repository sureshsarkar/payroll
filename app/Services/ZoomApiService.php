<?php

namespace App\Services;

use App\Models\ZoomCredential;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Zoom Server-to-Server OAuth API caller.
 *
 * Server-to-Server OAuth (replaces the legacy User-OAuth flow as of
 * 2026-05-08). Tokens are minted on demand from
 * account_id + client_id + client_secret. There are no refresh tokens
 * and no "OAuth dead" failure mode — if the credentials work, they
 * always work; if they don't, regenerating the secret in the Zoom
 * Marketplace fixes them once.
 *
 * Tokens have a ~1h TTL; we cache them in DB to avoid hitting the
 * token endpoint on every API call. Cache hit = `zoom_access_token`
 * + `zoom_token_expires_at` from the credential row. Cache miss =
 * issue a `grant_type=account_credentials` POST and persist.
 */
final class ZoomApiService
{
    private const API_BASE = 'https://api.zoom.us/v2';
    private const OAUTH_TOKEN_URL = 'https://zoom.us/oauth/token';

    /**
     * Mint (or return cached) S2S access token. Returns null if the
     * credential row is missing fields or Zoom rejects the auth.
     */
    public function ensureFreshAccessToken(ZoomCredential $cred): ?string
    {
        if (!$cred->account_id || !$cred->client_id || !$cred->client_secret) {
            return null;
        }

        // Cache hit — DB still has a fresh token. 60s safety margin so
        // we don't hand out a token that's about to expire mid-request.
        if ($cred->zoom_access_token
            && $cred->zoom_token_expires_at
            && now()->addSeconds(60)->lt($cred->zoom_token_expires_at)) {
            return $cred->zoom_access_token;
        }

        try {
            $response = Http::withBasicAuth($cred->client_id, $cred->client_secret)
                ->asForm()
                ->timeout(15)
                ->post(self::OAUTH_TOKEN_URL, [
                    'grant_type' => 'account_credentials',
                    'account_id' => $cred->account_id,
                ]);
        } catch (\Throwable) {
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();
        if (!isset($data['access_token'])) {
            return null;
        }

        $cred->forceFill([
            'zoom_access_token'     => $data['access_token'],
            'zoom_token_expires_at' => now()->addSeconds((int) ($data['expires_in'] ?? 3600)),
        ])->save();

        return $data['access_token'];
    }

    /**
     * Check if a meeting still exists on Zoom's side. Returns one of:
     *   ['ok',      'meeting found']                — 200, meeting reachable
     *   ['missing', 'meeting not found / deleted']  — 404
     *   ['error',   '<HTTP code> <message>']        — auth/rate-limit/etc
     *   ['skipped', '<reason>']                     — credential dead / missing
     *
     * @return array{0: 'ok'|'missing'|'error'|'skipped', 1: string}
     */
    public function probeMeeting(ZoomCredential $cred, string $meetingId): array
    {
        $accessToken = $this->ensureFreshAccessToken($cred);
        if (!$accessToken) {
            return ['skipped', 'instructor zoom credential cannot mint S2S token (missing fields or Zoom rejected auth)'];
        }

        try {
            $response = $this->client($accessToken)
                ->timeout(15)
                ->get(self::API_BASE . '/meetings/' . urlencode($meetingId));
        } catch (\Throwable $e) {
            return ['error', 'network: ' . substr($e->getMessage(), 0, 180)];
        }

        if ($response->successful()) {
            return ['ok', 'meeting found'];
        }

        if ($response->status() === 404) {
            return ['missing', 'meeting not found on Zoom (deleted or expired)'];
        }

        $body = $response->json();
        $msg = is_array($body) && isset($body['message'])
            ? (string) $body['message']
            : 'HTTP ' . $response->status();

        return ['error', sprintf('HTTP %d %s', $response->status(), substr($msg, 0, 120))];
    }

    /**
     * Fetch the Zoom Cloud Recordings for a meeting. Used by
     * recordings:sync. Returns the raw Zoom JSON `recording_files`
     * array — caller maps it to LiveClassRecording rows.
     *
     * @return array{0: 'ok'|'no_recordings'|'error'|'skipped', 1: array<int, array<string, mixed>>|string}
     */
    public function fetchMeetingRecordings(ZoomCredential $cred, string $meetingId): array
    {
        $accessToken = $this->ensureFreshAccessToken($cred);
        if (!$accessToken) {
            return ['skipped', 'instructor zoom credential cannot mint S2S token'];
        }

        try {
            $response = $this->client($accessToken)
                ->timeout(20)
                ->get(self::API_BASE . '/meetings/' . urlencode($meetingId) . '/recordings');
        } catch (\Throwable $e) {
            return ['error', 'network: ' . substr($e->getMessage(), 0, 180)];
        }

        if ($response->status() === 404) {
            // Zoom returns 404 both for "meeting doesn't exist" and "meeting
            // exists but has no cloud recording". Treat as no_recordings —
            // the caller already verifies meeting existence via probeMeeting.
            return ['no_recordings', []];
        }

        if (!$response->successful()) {
            $body = $response->json();
            $msg = is_array($body) && isset($body['message'])
                ? (string) $body['message']
                : ('HTTP ' . $response->status());
            return ['error', sprintf('HTTP %d %s', $response->status(), substr($msg, 0, 120))];
        }

        $data  = $response->json();
        $files = is_array($data['recording_files'] ?? null) ? $data['recording_files'] : [];

        return ['ok', $files];
    }

    /**
     * 2026-06-04 — End a live Zoom meeting (PUT /meetings/{id}/status {action:end}).
     * Returns true if Zoom ended it (204), it was already gone (404), or it
     * simply wasn't live (400 "not in progress") — all of which mean the
     * meeting is no longer occupying the host's single concurrent-meeting slot.
     */
    public function endMeeting(ZoomCredential $cred, string $meetingId): bool
    {
        if ($meetingId === '') {
            return false;
        }
        $accessToken = $this->ensureFreshAccessToken($cred);
        if (!$accessToken) {
            return false;
        }

        try {
            $response = $this->client($accessToken)
                ->timeout(15)
                ->put(self::API_BASE . '/meetings/' . urlencode($meetingId) . '/status', [
                    'action' => 'end',
                ]);
        } catch (\Throwable $e) {
            return false;
        }

        // 204 = ended; 404 = already gone; 400 = was not in progress.
        return $response->successful()
            || in_array($response->status(), [400, 404], true);
    }

    /**
     * 2026-06-04 — End every meeting the host currently has LIVE except the one
     * about to start. Zoom Basic/Pro allows only ONE concurrent meeting per
     * host, so a previous class that never ended blocks the next one with
     * "Already has other meetings in progress". Call this right before a host
     * starts a class so the slot is free.
     *
     * Returns the number of meetings ended (0 on any soft failure — best-effort).
     */
    public function endOtherLiveMeetings(ZoomCredential $cred, ?string $exceptMeetingId = null): int
    {
        $accessToken = $this->ensureFreshAccessToken($cred);
        if (!$accessToken) {
            return 0;
        }

        try {
            $response = $this->client($accessToken)
                ->timeout(15)
                ->get(self::API_BASE . '/users/me/meetings', ['type' => 'live']);
        } catch (\Throwable $e) {
            return 0;
        }
        if (!$response->successful()) {
            return 0;
        }

        $meetings = $response->json('meetings');
        if (!is_array($meetings)) {
            return 0;
        }

        $except = $exceptMeetingId !== null ? (string) $exceptMeetingId : null;
        $ended  = 0;
        foreach ($meetings as $m) {
            $id = (string) ($m['id'] ?? '');
            if ($id === '' || ($except !== null && $id === $except)) {
                continue;
            }
            if ($this->endMeeting($cred, $id)) {
                $ended++;
            }
        }
        return $ended;
    }

    private function client(string $accessToken): PendingRequest
    {
        return Http::withToken($accessToken)
            ->acceptJson()
            ->withHeaders(['Content-Type' => 'application/json']);
    }
}
