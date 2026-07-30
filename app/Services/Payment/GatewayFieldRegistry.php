<?php

namespace App\Services\Payment;

/**
 * Single source of truth describing every payment gateway's configurable
 * fields — which are SECRET (encrypted at rest + masked in the UI), which are
 * REQUIRED (a coach config missing these is treated as unusable and falls back
 * to the next priority tier), and which field carries the webhook/verification
 * secret.
 *
 * Field keys deliberately mirror the existing GLOBAL credential keys so the
 * resolver's output is a drop-in replacement at the checkout + webhook seams.
 *
 * `coreGateways()` are the ones wired end-to-end (checkout init + callback +
 * webhook) in phase 1. The rest are storage/UI-ready and extend the same way.
 */
final class GatewayFieldRegistry
{
    /**
     * @var array<string, array{label:string, fields:array<string,array{label:string,secret:bool,required:bool,type?:string,options?:array}>, webhook_secret_field:?string, default_currency:string}>
     */
    private const SPECS = [
        'razorpay' => [
            'label'  => 'Razorpay',
            'fields' => [
                'razorpay_key'            => ['label' => 'API Key',        'secret' => true,  'required' => true],
                'razorpay_secret'         => ['label' => 'API Secret',     'secret' => true,  'required' => true],
                'razorpay_webhook_secret' => ['label' => 'Webhook Secret', 'secret' => true,  'required' => false],
                'razorpay_name'           => ['label' => 'Display Name',   'secret' => false, 'required' => false],
                'razorpay_description'    => ['label' => 'Description',     'secret' => false, 'required' => false],
                'razorpay_theme_color'    => ['label' => 'Theme Color',    'secret' => false, 'required' => false, 'type' => 'color'],
            ],
            'webhook_secret_field' => 'razorpay_webhook_secret',
            'default_currency'     => 'INR',
        ],

        'stripe' => [
            'label'  => 'Stripe',
            'fields' => [
                'stripe_key'            => ['label' => 'Publishable Key',        'secret' => true, 'required' => true],
                'stripe_secret'         => ['label' => 'Secret Key',            'secret' => true, 'required' => true],
                'stripe_webhook_secret' => ['label' => 'Webhook Signing Secret', 'secret' => true, 'required' => false],
            ],
            'webhook_secret_field' => 'stripe_webhook_secret',
            'default_currency'     => 'USD',
        ],

        'paypal' => [
            'label'  => 'PayPal',
            'fields' => [
                'paypal_client_id'    => ['label' => 'Client ID',     'secret' => true,  'required' => true],
                'paypal_secret_key'   => ['label' => 'Client Secret', 'secret' => true,  'required' => true],
                'paypal_app_id'       => ['label' => 'App ID',        'secret' => false, 'required' => false],
                'paypal_account_mode' => ['label' => 'Account Mode',  'secret' => false, 'required' => false, 'type' => 'select', 'options' => ['sandbox', 'live']],
                'paypal_webhook_id'   => ['label' => 'Webhook ID',    'secret' => true,  'required' => false],
            ],
            'webhook_secret_field' => 'paypal_webhook_id',
            'default_currency'     => 'USD',
        ],

        // --- storage/UI-ready (not yet wired into checkout seams in phase 1) ---
        'mollie' => [
            'label'  => 'Mollie',
            'fields' => [
                'mollie_key' => ['label' => 'API Key', 'secret' => true, 'required' => true],
            ],
            'webhook_secret_field' => null,
            'default_currency'     => 'EUR',
        ],
        'instamojo' => [
            'label'  => 'Instamojo',
            'fields' => [
                'instamojo_client_id'     => ['label' => 'Client ID',     'secret' => true,  'required' => true],
                'instamojo_client_secret' => ['label' => 'Client Secret', 'secret' => true,  'required' => true],
                'instamojo_account_mode'  => ['label' => 'Account Mode',  'secret' => false, 'required' => false, 'type' => 'select', 'options' => ['Sandbox', 'Live']],
            ],
            'webhook_secret_field' => null,
            'default_currency'     => 'INR',
        ],
        'flutterwave' => [
            'label'  => 'Flutterwave',
            'fields' => [
                'flutterwave_public_key' => ['label' => 'Public Key',   'secret' => true,  'required' => true],
                'flutterwave_secret_key' => ['label' => 'Secret Key',   'secret' => true,  'required' => true],
                'flutterwave_app_name'   => ['label' => 'Display Name', 'secret' => false, 'required' => false],
            ],
            'webhook_secret_field' => null,
            'default_currency'     => 'NGN',
        ],
        'paystack' => [
            'label'  => 'Paystack',
            'fields' => [
                'paystack_public_key' => ['label' => 'Public Key', 'secret' => true, 'required' => true],
                'paystack_secret_key' => ['label' => 'Secret Key', 'secret' => true, 'required' => true],
            ],
            'webhook_secret_field' => null,
            'default_currency'     => 'NGN',
        ],
    ];

    /** Gateways wired end-to-end (checkout init + callback + webhook) in phase 1. */
    public static function coreGateways(): array
    {
        return ['razorpay', 'stripe', 'paypal'];
    }

    /** Every gateway the coach-specific storage/UI supports. */
    public static function allGateways(): array
    {
        return array_keys(self::SPECS);
    }

    public static function isKnown(string $gateway): bool
    {
        return isset(self::SPECS[strtolower($gateway)]);
    }

    public static function isCore(string $gateway): bool
    {
        return in_array(strtolower($gateway), self::coreGateways(), true);
    }

    /** @return array|null */
    public static function spec(string $gateway): ?array
    {
        return self::SPECS[strtolower($gateway)] ?? null;
    }

    public static function label(string $gateway): string
    {
        return self::SPECS[strtolower($gateway)]['label'] ?? ucfirst($gateway);
    }

    /** @return array<int,string> */
    public static function fieldKeys(string $gateway): array
    {
        return array_keys(self::SPECS[strtolower($gateway)]['fields'] ?? []);
    }

    /** @return array<int,string> field keys flagged secret => encrypted + masked */
    public static function secretFields(string $gateway): array
    {
        $fields = self::SPECS[strtolower($gateway)]['fields'] ?? [];
        return array_keys(array_filter($fields, fn ($f) => ! empty($f['secret'])));
    }

    /** @return array<int,string> field keys that must be present for the config to be usable */
    public static function requiredFields(string $gateway): array
    {
        $fields = self::SPECS[strtolower($gateway)]['fields'] ?? [];
        return array_keys(array_filter($fields, fn ($f) => ! empty($f['required'])));
    }

    public static function webhookSecretField(string $gateway): ?string
    {
        return self::SPECS[strtolower($gateway)]['webhook_secret_field'] ?? null;
    }

    public static function defaultCurrency(string $gateway): string
    {
        return self::SPECS[strtolower($gateway)]['default_currency'] ?? 'USD';
    }

    public static function isSecretField(string $gateway, string $field): bool
    {
        return in_array($field, self::secretFields($gateway), true);
    }
}
