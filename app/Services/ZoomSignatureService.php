<?php

namespace App\Services;

/**
 * Generates Zoom Meeting SDK signatures (HS256 JWT) entirely server-side.
 *
 * Reference: https://developers.zoom.us/docs/meeting-sdk/auth/
 *
 * Why this exists: the prior implementation passed the SDK Secret to the
 * browser (`{{ $instructor->zoom_credential->client_secret }}` rendered into
 * <script>) so any meeting attendee could view-source and steal the
 * instructor's Zoom credentials. Signature generation MUST stay server-side.
 *
 * The signature is a plain HS256 JWT — Zoom's verification just checks the
 * HMAC + claims; we don't need the firebase/php-jwt package since the
 * encoding is trivial.
 */
final class ZoomSignatureService
{
    /**
     * Build a signed JWT for ZoomMtg.join().
     *
     * @param string $sdkKey         Zoom SDK Key (a.k.a. Client ID) — public-ish
     * @param string $sdkSecret      Zoom SDK Secret — NEVER returned to the browser
     * @param string $meetingNumber  Numeric meeting id, no spaces
     * @param int    $role           1 = host, 0 = attendee
     * @param int    $ttlSeconds     Token lifetime; default 2h (Zoom max is 48h)
     * @return string  base64url-encoded JWT
     */
    public function generate(
        string $sdkKey,
        string $sdkSecret,
        string $meetingNumber,
        int $role,
        int $ttlSeconds = 7200,
    ): string {
        $iat = time() - 30;          // 30 s skew tolerance against clock drift
        $exp = $iat + $ttlSeconds;

        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        // Per Zoom docs the payload must contain BOTH `sdkKey` and `appKey`
        // (legacy alias) plus the meeting number and role.
        $payload = [
            'sdkKey'   => $sdkKey,
            'appKey'   => $sdkKey,
            'mn'       => (string) $meetingNumber,
            'role'     => $role,
            'iat'      => $iat,
            'exp'      => $exp,
            'tokenExp' => $exp,
        ];

        $b64 = static fn (string $bytes): string =>
            rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        $headerEnc  = $b64(json_encode($header, JSON_UNESCAPED_SLASHES));
        $payloadEnc = $b64(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signature  = $b64(hash_hmac('sha256', "$headerEnc.$payloadEnc", $sdkSecret, true));

        return "$headerEnc.$payloadEnc.$signature";
    }
}
