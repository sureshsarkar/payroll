<?php

namespace App\Services\Payment;

use App\Models\CoachPaymentGateway;
use Illuminate\Http\UploadedFile;

/**
 * Shared save logic for coach gateway configs, used by BOTH the Super-Admin
 * (manager_type=admin) and the Enterprise coach panel (manager_type=coach).
 *
 * Security-critical bits live here so both surfaces behave identically:
 *  - secret fields submitted blank KEEP the stored value (so the masked UI never
 *    has to round-trip a real secret),
 *  - credentials are persisted through the model's encrypted:array cast,
 *  - charge is clamped to 0..100,
 *  - unknown gateways are rejected.
 *
 * The caller is responsible for authorising the (coachId, managerType) pair.
 */
class CoachGatewayConfigService
{
    public function save(
        int $coachId,
        string $managerType,
        string $gateway,
        array $input,
        ?UploadedFile $image = null
    ): CoachPaymentGateway {
        $gateway = strtolower(trim($gateway));

        if (! GatewayFieldRegistry::isKnown($gateway)) {
            throw new \InvalidArgumentException("Unknown gateway: {$gateway}");
        }
        if (! in_array($managerType, [CoachPaymentGateway::MANAGER_COACH, CoachPaymentGateway::MANAGER_ADMIN], true)) {
            throw new \InvalidArgumentException("Invalid manager type: {$managerType}");
        }

        $row = CoachPaymentGateway::firstOrNew([
            'coach_id'     => $coachId,
            'gateway'      => $gateway,
            'manager_type' => $managerType,
        ]);

        $existing = is_array($row->credentials) ? $row->credentials : [];
        $creds    = [];

        foreach (GatewayFieldRegistry::fieldKeys($gateway) as $field) {
            $submitted = trim((string) ($input[$field] ?? ''));
            $isSecret  = GatewayFieldRegistry::isSecretField($gateway, $field);

            if ($isSecret && $submitted === '') {
                // Blank secret = "keep what's saved" (the UI shows it masked).
                if (array_key_exists($field, $existing)) {
                    $creds[$field] = $existing[$field];
                }
                continue;
            }
            $creds[$field] = $submitted;
        }

        $row->status      = (($input['status'] ?? 'inactive') === 'active') ? 'active' : 'inactive';
        $row->charge      = max(0.0, min(100.0, (float) ($input['charge'] ?? 0)));
        $row->currency_id = isset($input['currency_id']) && $input['currency_id'] !== ''
            ? (int) $input['currency_id'] : $row->currency_id;
        $row->credentials = $creds;

        if ($image instanceof UploadedFile && function_exists('file_upload')) {
            $row->image = file_upload($image, 'uploads/custom-images/coach-gateways/', $row->image);
        }

        $row->save();

        return $row;
    }
}
