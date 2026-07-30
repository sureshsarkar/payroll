<?php

namespace App\Services;

use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

/**
 * 2FA / TOTP service.
 *
 * Wraps pragmarx/google2fa for secret generation + verification, and
 * bacon-qr-code for the SVG QR shown during enrollment.
 *
 * Recovery codes: 8 single-use 10-character codes generated alongside the
 * secret. Each is consumed (removed from the array) on use.
 */
class TwoFactorAuthService
{
    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Generate a fresh 32-character base32 secret.
     */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    /**
     * Generate 8 recovery codes (each 10 characters, hyphenated for readability).
     * Format: XXXXX-XXXXX
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $a = $this->randomBase32(5);
            $b = $this->randomBase32(5);
            $codes[] = strtolower("$a-$b");
        }
        return $codes;
    }

    /**
     * otpauth:// URI for the QR — apps like Google/Microsoft Authenticator,
     * Authy, 1Password, etc. all consume this.
     */
    public function otpauthUrl(string $accountLabel, string $issuer, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl($issuer, $accountLabel, $secret);
    }

    /**
     * Render the QR for the otpauth URI as an inline SVG string.
     */
    public function qrSvg(string $otpauthUrl, int $size = 220): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd());
        $writer = new Writer($renderer);
        return $writer->writeString($otpauthUrl);
    }

    /**
     * Verify a 6-digit code against the user's secret.
     * Returns true if the code is correct (within ±1 window of clock skew).
     */
    public function verifyCode(string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (!ctype_digit($code) || strlen($code) !== 6) {
            return false;
        }
        return (bool) $this->google2fa->verifyKey($secret, $code, 1);
    }

    /**
     * Try to consume a recovery code. Returns true if matched (and removes it
     * from the user's stored list); false otherwise. Caller is responsible for
     * persisting $user after a successful consume.
     *
     * Works with any model that has the encrypted-array `two_factor_recovery_codes`
     * cast (Admin, User, ...).
     */
    public function consumeRecoveryCode($user, string $submittedCode): bool
    {
        $submitted = strtolower(trim($submittedCode));
        $codes = $user->two_factor_recovery_codes ?? [];
        if (!is_array($codes) || empty($codes)) {
            return false;
        }
        $idx = array_search($submitted, array_map('strtolower', $codes), true);
        if ($idx === false) {
            return false;
        }
        unset($codes[$idx]);
        $user->two_factor_recovery_codes = array_values($codes);
        return true;
    }

    /**
     * 5-char chunk of the recovery-code alphabet (digits + lowercase letters
     * minus visually-ambiguous ones: 0, O, 1, I, l).
     */
    private function randomBase32(int $len): string
    {
        $alphabet = '23456789abcdefghjkmnpqrstuvwxyz';
        $max = strlen($alphabet) - 1;
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    }
}
