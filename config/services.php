<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'stripe' => [
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'razorpay' => [
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],

    'bkash' => [
        'app_key'    => env('BKASH_APP_KEY'),
        'app_secret' => env('BKASH_APP_SECRET'),
        'username'   => env('BKASH_USERNAME'),
        'password'   => env('BKASH_PASSWORD'),
        'base_url'   => env('BKASH_BASE_URL', 'https://tokenized.sandbox.bka.sh/v1.2.0-beta'),
    ],

    'paypal' => [
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
    ],

    'mercadopago' => [
        'access_token' => env('MP_ACCESS_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | YouTube Data API v3 — platform fallback key
    |--------------------------------------------------------------------------
    |
    | Used by App\Services\Site\YouTubeFetcher when a coach hasn't added their
    | own YouTube API key via /instructor/youtube-credentials. With this set,
    | any coach can drop a YouTube channel ID into the YouTube / Recorded
    | Courses sections and videos render immediately — no per-coach Google
    | Cloud project required.
    |
    | Default quota: 10,000 units/day. Each search.list call costs 100 units.
    | Combined with the 24h cache in YouTubeFetcher this comfortably serves
    | ~100 distinct coach channels per day per platform install. Heavier use
    | should still register their own key in youtube_credentials (which
    | always wins over this fallback).
    |
    */

    'youtube' => [
        'api_key' => env('YOUTUBE_API_KEY'),
    ],

];
