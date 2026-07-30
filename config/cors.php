<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS) Configuration
|--------------------------------------------------------------------------
|
| SECURITY (audit 2026-05-22) — Originally `allowed_origins: ['*']` +
| `allowed_methods: ['*']` + `allowed_headers: ['*']`. Wildcard CORS lets
| any website's JavaScript hit the API and read the response. Since
| `supports_credentials: false`, browser cookies aren't sent, but any
| bearer-token an attacker phishes from a user can still hit the API
| from a malicious origin.
|
| This file now reads three env vars so you can tighten CORS without
| code changes:
|
|   CORS_ALLOWED_ORIGINS=https://mbsguru.com,https://acme.mbsguru.com,https://app.example.com
|   CORS_ALLOWED_ORIGINS_REGEX=^https:\/\/[a-z0-9-]+\.mbsguru\.com$
|   CORS_ALLOWED_METHODS=GET,POST,PUT,PATCH,DELETE,OPTIONS
|
| The OLD '*' behaviour is preserved as the default fallback so this
| file ships without breaking existing deploys. To enable tightening,
| operator just adds CORS_ALLOWED_ORIGINS to .env, runs config:cache,
| and reloads.
|
| Recommended setup once mobile + coach white-label domains are known:
|   CORS_ALLOWED_ORIGINS=https://mbsguru.com,https://www.mbsguru.com
|   CORS_ALLOWED_ORIGINS_REGEX=^https://[a-z0-9-]+\.mbsguru\.com$
|
| The regex covers every coach's white-label subdomain in one rule.
| Mobile apps (no Origin header) and server-to-server calls are
| unaffected — CORS only filters browser requests.
*/

$envOrigins = env('CORS_ALLOWED_ORIGINS');
$envMethods = env('CORS_ALLOWED_METHODS');
$envHeaders = env('CORS_ALLOWED_HEADERS');
$envRegex   = env('CORS_ALLOWED_ORIGINS_REGEX');

// Dev shortcut: CORS_ALLOWED_ORIGINS_REGEX_DEV=1 in .env enables a
// localhost-with-any-port regex. Avoids dotenv backslash-escape pain.
// In production, drop this env var and set CORS_ALLOWED_ORIGINS_REGEX
// to your white-label subdomain pattern, e.g.
//   /^https:\/\/[a-z0-9-]+\.mbsguru\.com$/
$devRegex = env('CORS_ALLOWED_ORIGINS_REGEX_DEV')
    ? '#^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$#'
    : null;

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => $envMethods
        ? array_map('trim', explode(',', $envMethods))
        : ['*'],

    // F53 (audit 2026-06-26) — closed default. Previously fell back to ['*']
    // when CORS_ALLOWED_ORIGINS was unset, so a deploy that forgot the env var
    // silently got wildcard CORS. Default to the platform's own origin; coach
    // white-label subdomains are covered by allowed_origins_patterns below.
    // Set CORS_ALLOWED_ORIGINS (comma-separated) to add more.
    'allowed_origins' => $envOrigins
        ? array_filter(array_map('trim', explode(',', $envOrigins)))
        : array_filter([config('app.url')]),

    // Optional regex covering coach white-label subdomains.
    'allowed_origins_patterns' => array_filter([
        $envRegex,
        $devRegex,
    ]),

    'allowed_headers' => $envHeaders
        ? array_map('trim', explode(',', $envHeaders))
        : ['*'],

    'exposed_headers' => [],

    'max_age' => (int) env('CORS_MAX_AGE', 0),

    'supports_credentials' => false,

];
