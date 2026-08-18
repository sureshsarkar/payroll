<?php

use App\Enums\ThemeList;
use App\Exceptions\AccessPermissionDeniedException;
use App\Models\CoachStaff;
use App\Models\Course;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\BasicPayment\app\Models\BasicPayment;
use Modules\BasicPayment\app\Models\PaymentGateway;
use Modules\BkashPG\app\Models\BkashPGModel;
use Modules\CryptoPayment\app\Models\CryptoPG;
use Modules\Currency\app\Models\MultiCurrency;
use Modules\GlobalSetting\app\Models\CustomCode;
use Modules\GlobalSetting\app\Models\Setting;
use Modules\Language\app\Models\Language;
use Modules\Location\app\Models\Country;
use Modules\MercadoPagoPG\app\Models\MercadoPagoPG;
use Modules\Order\app\Models\Enrollment;
use Nwidart\Modules\Facades\Module;
use Spatie\LaravelImageOptimizer\Facades\ImageOptimizer;



/**
 * Server-side allowlist of file extensions accepted by the public upload
 * helpers. Anything not in this list is refused with a RuntimeException —
 * even if a caller forgets to validate.
 *
 * Why an allowlist (not a denylist): the LFM-style `disallowed_extensions`
 * list inevitably misses something (phtml, phar, php5, htaccess, svg-with-
 * <script>, …). Allowlists fail closed.
 */
const FILE_UPLOAD_ALLOWED_EXTS = [
    // raster images
    'jpg','jpeg','png','gif','webp','bmp','ico',
    // documents
    'pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv',
    // media
    'mp3','mp4','wav','ogg','webm',
    // fonts (page-template-builder uses these)
    'woff','woff2','ttf','otf','eot',
    // archives (page-template-builder)
    'zip',
];

function _sanitize_upload_basename(string $name): string
{
    // Strip any directory component the client tried to slip in.
    $name = basename($name);
    // Lowercase + collapse unsafe chars to '-'.
    $name = strtolower($name);
    $name = preg_replace('/[^a-z0-9._-]+/', '-', $name) ?: '';
    // No leading dots (avoid `.htaccess`).
    $name = ltrim($name, '.');
    return $name === '' ? 'file' : $name;
}

function _assert_safe_upload_extension(UploadedFile $file): string
{
    // Prefer the real (server-detected) extension when available — falls
    // back to the client's claimed extension, which is what attackers
    // control.
    $ext = strtolower((string) $file->guessExtension() ?: $file->getClientOriginalExtension());
    if ($ext === '' || !in_array($ext, FILE_UPLOAD_ALLOWED_EXTS, true)) {
        throw new \RuntimeException("Upload rejected: extension '$ext' is not allowed.");
    }
    return $ext;
}

function file_template_upload(UploadedFile $file, string $path = 'uploads/page-template-builder/', ?string $oldFile = '', bool $optimize = false)
{
    $ext  = _assert_safe_upload_extension($file);
    $base = _sanitize_upload_basename(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

    // time() prefix prevents collisions; the strip-traversal sanitizer above
    // makes the basename safe; the extension comes from the verified allowlist.
    $finalName = time() . '-' . $base . '.' . $ext;
    $file->move(public_path($path), $finalName);

    $filePath = $path . $finalName;

    try {
        if ($oldFile && File::exists(public_path($oldFile))) {
            File::delete(public_path($oldFile));
        }
        if ($optimize) {
            ImageOptimizer::optimize(public_path($filePath));
        }
    } catch (\Exception $e) {
        \Log::info($e->getMessage());
    }

    return $filePath;
}

function file_upload(UploadedFile $file, string $path = 'uploads/custom-images/', ?string $oldFile = '', bool $optimize = false)
{
    $ext = _assert_safe_upload_extension($file);

    // Auto-generated filename — never trust the client-supplied basename.
    $file_name = 'wsus-img' . date('-Y-m-d-h-i-s-') . rand(999, 9999) . '.' . $ext;
    $relative  = $path . $file_name;
    $file->move(public_path($path), $file_name);

    try {
        if ($oldFile && ! str($oldFile)->contains('uploads/website-images') && File::exists(public_path($oldFile))) {
            File::delete(public_path($oldFile));
        }
        if ($optimize) {
            ImageOptimizer::optimize(public_path($relative));
        }
    } catch (Exception $e) {
        Log::info($e->getMessage());
    }

    return $relative;
}
// file upload method
if (! function_exists('allLanguages')) {
    function allLanguages()
    {
        $allLanguages = Cache::rememberForever('allLanguages', function () {
            return Language::select('code', 'name', 'direction', 'status')->get();
        });

        if (! $allLanguages) {
            $allLanguages = Language::select('code', 'name', 'direction', 'status')->get();
        }

        return $allLanguages;
    }
}

if (! function_exists('allCurrencies')) {
    function allCurrencies()
    {
        $allCurrencies = Cache::rememberForever('allCurrencies', function () {
            return MultiCurrency::all();
        });

        if (! $allCurrencies) {
            $allCurrencies = MultiCurrency::all();
        }

        return $allCurrencies;
    }
}

if (! function_exists('getSessionLanguage')) {
    function getSessionLanguage(): string
    {
        if (! session()->has('lang')) {
            session()->put('lang', config('app.locale'));
            session()->forget('text_direction');
            session()->put('text_direction', 'ltr');
        }

        $lang = Session::get('lang');

        return $lang;
    }
}

if (! function_exists('getSessionCurrency')) {
    function getSessionCurrency(): string
    {
        if (! session()->has('currency_code') || ! session()->has('currency_rate') || ! session()->has('currency_position')) {
            $currency = allCurrencies()->where('is_default', 'yes')->first();
            session()->put('currency_code', $currency->currency_code);
            session()->forget('currency_position');
            session()->put('currency_position', $currency->currency_position);
            session()->forget('currency_icon');
            session()->put('currency_icon', $currency->currency_icon);
            session()->forget('currency_rate');
            session()->put('currency_rate', $currency->currency_rate);
        }

        return Session::get('currency_code');
    }
}

function admin_lang()
{
    return Session::get('admin_lang');
}
if (! function_exists('getSocialLinks')) {
    function getSocialLinks()
    {
        return Cache::rememberForever('getSocialLinks', function () {
            return \Modules\SocialLink\app\Models\SocialLink::select('link', 'icon', 'name')->get();
        });
    }
}

if (! function_exists('coachCommerceUrl')) {
    /**
     * 2026-06-10 — White-label commerce URL.
     *
     * On a VERIFIED coach DOMAIN (resolved_coach_id stamped by
     * ResolveCoachByDomain) it returns the CLEAN ROOT url — photongears.io/cart,
     * /checkout — so the coach site keeps its own domain at the root. On the
     * path surface (/coach/{slug}/...) it returns the slug-prefixed url so that
     * mode keeps working. $segment is the bare path ('cart' | 'checkout').
     * Never trusts client input — the domain context is server-resolved.
     */
    function coachCommerceUrl(string $segment, ?string $coachSlug = null): string
    {
        $segment = ltrim($segment, '/');
        if ((int) request()->attributes->get('resolved_coach_id') > 0) {
            return url('/' . $segment);                 // clean root URL on a coach domain
        }
        return url('/coach/' . trim((string) $coachSlug, '/') . '/' . $segment);
    }
}

if (! function_exists('orderInvoiceBrand')) {
    /**
     * 2026-06-12 — White-label invoice "Billed From" brand.
     *
     * Returns the OWNING COACH's brand for an order (name / logo / email /
     * phone) so a coach's invoice shows the COACH — not the platform's
     * "MBSGuru". Resolution: order.primary_coach_id → seller_id → the first
     * item's course.instructor_id. Falls back to the platform setting only for
     * genuine platform-direct orders. Never throws (invoices must always render).
     */
    function orderInvoiceBrand($order): object
    {
        $platform = cache('setting');
        $fallback = (object) [
            'name'     => $platform->app_name ?? config('app.name'),
            'logo'     => ! empty($platform->logo) ? asset($platform->logo) : null,
            'email'    => $platform->contact_message_receiver_mail ?? null,
            'phone'    => null,
            'address'  => $platform->site_address ?? null,
            'is_coach' => false,
        ];

        try {
            $coachId = (int) ($order->primary_coach_id ?? $order->seller_id ?? 0);
            if ($coachId <= 0) {
                $coachId = (int) (optional(optional($order->orderItems->first())->course)->instructor_id ?? 0);
            }
            if ($coachId <= 0) {
                return $fallback;
            }

            $brand = app(\App\Services\BrandResolver::class)->forCoach($coachId);
            $coach = \App\Models\User::find($coachId);

            $name = ($brand && ! $brand->isPlatformDefault && ! empty($brand->name))
                ? $brand->name
                : ($coach->name ?? $fallback->name);

            // Only the coach's OWN uploaded logo — never the platform logo.
            $logo = ($brand && ($brand->ownLogo ?? false) && ! ($brand->isPlatformDefault ?? true)
                     && method_exists($brand, 'logoUrl') && $brand->logoUrl())
                ? $brand->logoUrl()
                : null;

            return (object) [
                'name'     => $name,
                'logo'     => $logo,
                'email'    => ($brand && ! empty($brand->supportEmail)) ? $brand->supportEmail : ($coach->email ?? null),
                'phone'    => ($brand && ! empty($brand->supportPhone)) ? $brand->supportPhone : null,
                'address'  => null, // coach addresses aren't stored; never leak the platform address on a coach invoice
                'is_coach' => true,
            ];
        } catch (\Throwable $e) {
            return $fallback;
        }
    }
}

// calculate currency
function currency($price)
{
    getSessionCurrency();
    $currency_icon = Session::get('currency_icon');
    
    $currency_rate = Session::has('currency_rate') ? Session::get('currency_rate') : 1;
    $currency_position = Session::get('currency_position');

    $price = $price * $currency_rate;
    $price = number_format($price, 2, '.', ',');

    if ($currency_position == 'before_price') {
        $price = $currency_icon.$price;
    } elseif ($currency_position == 'before_price_with_space') {
        $price = $currency_icon.' '.$price;
    } elseif ($currency_position == 'after_price') {
        $price = $price.$currency_icon;
    } elseif ($currency_position == 'after_price_with_space') {
        $price = $price.' '.$currency_icon;
    } else {
        $price = $currency_icon.$price;
    }

    return $price;
}

if (! function_exists('formatMoney')) {
    /**
     * 2026-07-09 (Email/Notification audit — Phase 1.3).
     *
     * Session-INDEPENDENT money formatter for cron / queue / webhook contexts
     * (notification emails) where currency() would (a) depend on a web session
     * that doesn't exist and (b) multiply by the session currency_rate.
     *
     * The amount is treated as ALREADY being in $code (or the platform default
     * when $code is null) — no rate conversion — and formatted with that
     * currency's icon + position. Falls back safely to the default currency.
     */
    function formatMoney($amount, ?string $code = null): string
    {
        $amount = (float) $amount;
        $rows = allCurrencies();

        $cur = null;
        if ($code) {
            $cur = $rows->firstWhere('currency_code', $code);
        }
        if (! $cur) {
            $cur = $rows->firstWhere('is_default', 'yes') ?: $rows->first();
        }

        $icon = $cur->currency_icon ?? '';
        $position = $cur->currency_position ?? 'before_price';
        $number = number_format($amount, 2, '.', ',');

        return match ($position) {
            'before_price_with_space' => $icon.' '.$number,
            'after_price' => $number.$icon,
            'after_price_with_space' => $number.' '.$icon,
            default => $icon.$number, // before_price
        };
    }
}

if (! function_exists('formatMoneyCur')) {
    /**
     * 2026-07-17 (AUD-028) — session-independent money formatter for a KNOWN transaction
     * currency that is ALWAYS currency-unambiguous.
     *
     * When $code is a configured currency it renders with that currency's icon/position
     * via formatMoney(). When $code is NOT configured in the multi_currencies table (or
     * is the UNKNOWN exception bucket / blank), it renders the numeric amount with the
     * ISO code appended — so a $/₹ fallback icon can never silently mislabel, say, an AED
     * amount as rupees. The current session exchange rate is NEVER applied (historical
     * figures keep their transaction-currency value).
     */
    function formatMoneyCur($amount, ?string $code): string
    {
        $amount = (float) $amount;
        $code = strtoupper(trim((string) $code));
        $cur = $code !== '' ? allCurrencies()->firstWhere('currency_code', $code) : null;
        if ($cur) {
            return formatMoney($amount, $code);
        }
        // Unconfigured code / UNKNOWN exception — number + explicit code, no misleading icon.
        return number_format($amount, 2, '.', ',') . ' ' . ($code !== '' ? $code : 'UNKNOWN');
    }
}

if (! function_exists('strip_unresolved_tokens')) {
    /**
     * 2026-07-09 (Email/Notification audit — Phase 1.7).
     *
     * Removes any leftover {{token}} placeholder from a rendered email/message so
     * a raw token never reaches the recipient. Used by the LEGACY str_replace mail
     * path (order/payment/QnA/live-class), which only replaces a fixed token set —
     * any admin-added token would otherwise render literally.
     */
    function strip_unresolved_tokens(?string $text): string
    {
        return preg_replace('/\{\{\s*[a-zA-Z0-9_.]+\s*\}\}/', '', (string) $text);
    }
}

// calculate currency

if (! function_exists('userAuth')) {
    function userAuth()
    {
        return Auth::guard('web')->user();
    }
}
if (! function_exists('currentCompany')) {
    /**
     * The active tenant Company bound by EnsureCompanyContext, or null when
     * unbound (CLI, seeders, backfill, Super Admin) — callers/scope must treat
     * null as "no tenant filter".
     *
     * @return \Modules\Company\app\Models\Company|null
     */
    function currentCompany()
    {
        return app()->bound('currentCompany') ? app('currentCompany') : null;
    }
}
if (! function_exists('adminAuth')) {
    function adminAuth()
    {
        return Auth::guard('admin')->user();
    }
}

// custom decode and encode input value
function html_decode($text)
{
    $after_decode = htmlspecialchars_decode($text, ENT_QUOTES);

    return $after_decode;
}

if (! function_exists('checkAdminHasPermission')) {
    function checkAdminHasPermission($permission): bool
    {
        // Defensive: if no admin is authenticated (e.g. middleware misconfigured
        // on a route or this is called pre-auth), don't blow up with
        // "Call to a member function can() on null" — return false so the
        // caller can render or redirect appropriately.
        $admin = Auth::guard('admin')->user();
        return $admin ? (bool) $admin->can($permission) : false;
    }
}

if (! function_exists('checkAdminHasPermissionAndThrowException')) {
    function checkAdminHasPermissionAndThrowException($permission)
    {
        if (! checkAdminHasPermission($permission)) {
            throw new AccessPermissionDeniedException;
        }
    }
}

if (! function_exists('currentAdminPageTitle')) {
    /**
     * Audit follow-on (2026-05-12) — derive a friendly page title from
     * the current admin route name. Powers the master-layout navbar
     * <h1> so pages don't have to declare `@section('page-title','…')`
     * by hand to get a meaningful title.
     *
     * Resolution order:
     *   1. If the view yielded a `page-title` section, that wins (master
     *      layout passes this in as the explicit-override arg).
     *   2. Otherwise, lookup the route name in the small alias map below
     *      for routes that don't humanize cleanly.
     *   3. Otherwise, take the last segment of the route name
     *      ('admin.coach-landing-pages.index' → 'coach landing pages')
     *      and title-case it.
     *   4. Fallback to "Dashboard".
     *
     * Never throws — defensive against missing/null routes during
     * password-reset or auth views.
     */
    function currentAdminPageTitle(?string $explicit = null): string
    {
        if (!empty($explicit)) {
            return $explicit;
        }

        // Route-name → friendly label map. Only routes whose route-name
        // segment doesn't naturally read well need an entry here.
        static $aliases = [
            'admin.dashboard'                                  => 'Dashboard',
            'admin.edit-profile'                               => 'Edit Profile',
            'admin.settings'                                   => 'Settings',
            'admin.2fa.setup'                                  => 'Two-Factor Authentication',
            'admin.2fa.challenge'                              => 'Two-Factor Verification',
            'admin.role.index'                                 => 'Roles',
            'admin.admin.index'                                => 'Admins',
            'admin.membership-plans.index'                     => 'Membership Plans',
            'admin.user-memberships.index'                     => 'User Memberships',
            'admin.user-memberships.conversion'                => 'Trial Conversion',
            'admin.referral-commissions.index'                 => 'Referral Commissions',
            'admin.referrals.index'                            => 'Referrals',
            'admin.referrals.settings'                         => 'Referral Settings',
            'admin.coach-landing-pages.index'                  => 'Coach Landing Pages',
            'admin.coach-landing-pages.templates-report'       => 'Template Performance',
            'admin.coach-landing-pages.enquiries'              => 'Landing Page Enquiries',
            'admin.zoom-health.index'                          => 'Zoom Health',
            'admin.notifications.index'                        => 'Notifications',
        ];

        $route = request()->route();
        $name  = $route?->getName() ?? '';

        if ($name !== '' && isset($aliases[$name])) {
            return $aliases[$name];
        }

        // Humanize the last segment of the route name.
        if ($name !== '') {
            $segments = explode('.', $name);
            $last = (string) end($segments);
            if ($last !== '' && $last !== 'index') {
                return \Illuminate\Support\Str::headline(str_replace('-', ' ', $last));
            }
            // For *.index routes, use the resource segment (the second-to-last).
            if ($last === 'index' && count($segments) >= 2) {
                $resource = $segments[count($segments) - 2];
                return \Illuminate\Support\Str::headline(str_replace('-', ' ', $resource));
            }
        }

        return 'Dashboard';
    }
}

if (! function_exists('isAdminSettingsRoute')) {
    /**
     * Audit fix H5 (2026-05-12) — true when the current request is for an
     * admin "settings" page (general settings, currency, languages, payment,
     * email-template, admin/role CRUD, cache-clear, addons, etc.). The
     * master layout uses this to swap to a settings-sub-sidebar.
     *
     * Adding a new settings page is a one-line append below; previously
     * required hunting down a buried request()->routeIs(...) chain inside
     * the master_layout blade.
     *
     * @return bool
     */
    function isAdminSettingsRoute(): bool
    {
        static $patterns = [
            'admin.general-setting',
            'admin.marketing-setting',
            'admin.commission-setting',
            'admin.credential-setting',
            'admin.email-configuration',
            'admin.edit-email-template',
            'admin.currency.*',
            'admin.seo-setting',
            'admin.custom-code',
            'admin.cache-clear',
            'admin.cache-clear-confirm',
            'admin.database-clear',
            'admin.system-update.index',
            'admin.addons.*',
            'admin.admin.*',
            'admin.languages.*',
            'admin.basicpayment',
            'admin.paymentgateway',
            'admin.role.*',
        ];
        return request()->routeIs(...$patterns);
    }
}

if (! function_exists('getSettingStatus')) {
    function getSettingStatus($key)
    {
        if (Cache::has('setting')) {
            $setting = Cache::get('setting');
            if (! is_null($key)) {
                return $setting->$key == 'active' ? true : false;
            }
        } else {
            try {
                return Setting::where('key', $key)->first()?->value == 'active' ? true : false;
            } catch (Exception $e) {
                if (app()->isLocal()) {
                    Log::info($e->getMessage());
                }

                return false;
            }
        }

        return false;
    }
}
if (! function_exists('checkCrentials')) {
    function checkCrentials()
    {
        if (Cache::has('setting') && $settings = Cache::get('setting')) {
            if ($settings->recaptcha_status !== 'inactive' && ($settings->recaptcha_site_key == 'recaptcha_site_key' || $settings->recaptcha_secret_key == 'recaptcha_secret_key' || $settings->recaptcha_site_key == '' || $settings->recaptcha_secret_key == '')) {
                return (object) [
                    'status' => true,
                    'message' => __('Google Recaptcha credentails not found'),
                    'description' => __('This may create a problem while submitting any form submission from website. Please fill up the credential from google account.'),
                    'route' => 'admin.credential-setting',
                ];
            }

            if ($settings->pixel_status !== 'inactive' && ($settings->pixel_app_id == 'pixel_app_id' || $settings->pixel_app_id == '')) {
                return (object) [
                    'status' => true,
                    'message' => __('Facebook Pixel credentails not found'),
                    'description' => __('This may create a problem to analyze your website. Please fill up the credential to avoid any problem.'),
                    'route' => 'admin.credential-setting',
                ];
            }

            if ($settings->facebook_login_status !== 'inactive' && ($settings->facebook_app_id == 'facebook_app_id' || $settings->facebook_app_secret == 'facebook_app_secret' || $settings->facebook_redirect_url == 'facebook_redirect_url' || $settings->facebook_app_id == '' || $settings->facebook_app_secret == '' || $settings->facebook_redirect_url == '')) {
                return (object) [
                    'status' => true,
                    'message' => __('Facebook login credentails not found'),
                    'description' => __('This may create a problem while logging in using facebook. Please fill up the credential to avoid any problem.'),
                    'route' => 'admin.credential-setting',
                ];
            }

            if ($settings->google_login_status !== 'inactive' && ($settings->gmail_client_id == 'gmail_client_id' || $settings->gmail_secret_id == 'gmail_secret_id' || $settings->gmail_client_id == '' || $settings->gmail_secret_id == '')) {
                return (object) [
                    'status' => true,
                    'message' => __('Google login credentails not found'),
                    'description' => __('This may create a problem while logging in using google. Please fill up the credential to avoid any problem.'),
                    'route' => 'admin.credential-setting',
                ];
            }

            if ($settings->google_tagmanager_status !== 'inactive' && ($settings->google_tagmanager_id == 'google_tagmanager_id' || $settings->google_tagmanager_id == '')) {
                return (object) [
                    'status' => true,
                    'message' => __('Google tag manager credentials not found'),
                    'description' => __('This may create a problem to analyze your website. Please fill up the credential to avoid any problem.'),
                    'route' => 'admin.credential-setting',
                ];
            }
            if ($settings->google_analytic_status !== 'inactive' && ($settings->google_analytic_id == 'google_analytic_id' || $settings->google_analytic_id == '')) {
                return (object) [
                    'status' => true,
                    'message' => __('Google analytic credentials not found'),
                    'description' => __('This may create a problem to analyze your website. Please fill up the credential to avoid any problem.'),
                    'route' => 'admin.credential-setting',
                ];
            }

            if ($settings->tawk_status !== 'inactive' && ($settings->tawk_chat_link == 'tawk_chat_link' || $settings->tawk_chat_link == '')) {
                return (object) [
                    'status' => true,
                    'message' => __('Tawk Chat Link credentails not found'),
                    'description' => __('This may create a problem to analyze your website. Please fill up the credential to avoid any problem.'),
                    'route' => 'admin.credential-setting',
                ];
            }

            if ($settings->pusher_status !== 'inactive' && ($settings->pusher_app_id == 'pusher_app_id' || $settings->pusher_app_key == 'pusher_app_key' || $settings->pusher_app_secret == 'pusher_app_secret' || $settings->pusher_app_cluster == 'pusher_app_cluster' || $settings->pusher_app_id == '' || $settings->pusher_app_key == '' || $settings->pusher_app_secret == '' || $settings->pusher_app_cluster == '')) {
                return (object) [
                    'status' => true,
                    'message' => __('Pusher credentails not found'),
                    'description' => __('This may create a problem while logging in using google. Please fill up the credential to avoid any problem.'),
                    'route' => 'admin.credential-setting',
                ];
            }

            if ($settings->mail_host == 'mail_host' || $settings->mail_username == 'mail_username' || $settings->mail_password == 'mail_password' || $settings->mail_host == '' || $settings->mail_port == '' || $settings->mail_username == '' || $settings->mail_password == '') {
                return (object) [
                    'status' => true,
                    'message' => __('Mail credentails not found'),
                    'description' => __('This may create a problem while sending email. Please fill up the credential to avoid any problem.'),
                    'route' => 'admin.email-configuration',
                ];
            }
            if ($settings->wasabi_status !== 'inactive' && ($settings->wasabi_access_id == 'wasabi_access_id' || $settings->wasabi_access_id == '' || $settings->wasabi_secret_key == 'wasabi_secret_key' || $settings->wasabi_secret_key == '' || $settings->wasabi_bucket == 'wasabi_secret_key' || $settings->wasabi_bucket == '' || $settings->wasabi_region == 'wasabi_region' || $settings->wasabi_region == '')) {
                return (object) [
                    'status' => true,
                    'message' => __('Wasabi cloud storage credentials not found'),
                    'description' => __('This may create a problem to analyze your website. Please fill up the credential to avoid any problem.'),
                    'route' => 'admin.credential-setting',
                ];
            }
            if ($settings->aws_status !== 'inactive' && ($settings->aws_access_id == 'aws_access_id' || $settings->aws_access_id == '' || $settings->aws_secret_key == 'aws_secret_key' || $settings->aws_secret_key == '' || $settings->aws_bucket == 'aws_secret_key' || $settings->aws_bucket == '' || $settings->aws_region == 'aws_region' || $settings->aws_region == '')) {
                return (object) [
                    'status' => true,
                    'message' => __('AWS cloud storage credentials not found'),
                    'description' => __('This may create a problem to analyze your website. Please fill up the credential to avoid any problem.'),
                    'route' => 'admin.credential-setting',
                ];
            }
        }

        if (! Cache::has('basic_payment') && Module::isEnabled('BasicPayment')) {
            Cache::rememberForever('basic_payment', function () {
                $payment_info = BasicPayment::get();
                $basic_payment = [];
                foreach ($payment_info as $payment_item) {
                    $basic_payment[$payment_item->key] = $payment_item->value;
                }

                return (object) $basic_payment;
            });
        }

        if (Cache::has('basic_payment') && $basicPayment = Cache::get('basic_payment')) {
            if ($basicPayment->stripe_status !== 'inactive' && ($basicPayment->stripe_key == 'stripe_key' || $basicPayment->stripe_secret == 'stripe_secret' || $basicPayment->stripe_key == '' || $basicPayment->stripe_secret == '')) {

                return (object) [
                    'status' => true,
                    'message' => __('Stripe credentails not found'),
                    'description' => __('This may create a problem while making payment. Please fill up the credential to avoid any problem.'),
                    'route' => 'admin.basicpayment',
                ];
            }

            if ($basicPayment->paypal_status !== 'inactive' && ($basicPayment->paypal_client_id == 'paypal_client_id' || $basicPayment->paypal_secret_key == 'paypal_secret_key' || $basicPayment->paypal_client_id == '' || $basicPayment->paypal_secret_key == '')) {
                return (object) [
                    'status' => true,
                    'message' => __('Paypal credentails not found'),
                    'description' => __('This may create a problem while making payment. Please fill up the credential to avoid any problem.'),
                    'route' => 'admin.basicpayment',
                ];
            }
        }

        if (! Cache::has('payment_setting') && Module::isEnabled('BasicPayment')) {
            Cache::rememberForever('payment_setting', function () {
                $payment_info = PaymentGateway::get();
                $payment_setting = [];
                foreach ($payment_info as $payment_item) {
                    $payment_setting[$payment_item->key] = $payment_item->value;
                }
                // Audit 2026-05-18 phase 5 — transparently decrypt secret
                // keys (razorpay_secret / paystack_secret_key / etc.) when
                // building the cache. Legacy plaintext values pass through
                // unchanged (decrypt() checks the enc:v1: prefix first).
                $payment_setting = \App\Support\SecretSettings::decryptForTable('payment_gateways', $payment_setting);

                return (object) $payment_setting;
            });
        }

        if (Cache::has('payment_setting') && $paymentAddons = Cache::get('payment_setting')) {
            if ($paymentAddons->razorpay_status !== 'inactive' && ($paymentAddons->razorpay_key == 'razorpay_key' || $paymentAddons->razorpay_secret == 'razorpay_secret' || $paymentAddons->razorpay_key == '' || $paymentAddons->razorpay_secret == '')) {
                return (object) [
                    'status' => true,
                    'message' => __('Razorpay credentails not found'),
                    'description' => __('This may create a problem while making payment. Please fill up the credential to avoid any problem.'),
                    'route' => 'admin.paymentgateway',
                ];
            }

            if ($paymentAddons->flutterwave_status !== 'inactive' && ($paymentAddons->flutterwave_public_key == 'flutterwave_public_key' || $paymentAddons->flutterwave_secret_key == 'flutterwave_secret_key' || $paymentAddons->flutterwave_public_key == '' || $paymentAddons->flutterwave_secret_key == '')) {
                return (object) [
                    'status' => true,
                    'message' => __('Flutterwave credentails not found'),
                    'description' => __('This may create a problem while making payment. Please fill up the credential to avoid any problem.'),
                    'route' => 'admin.paymentgateway',
                ];
            }

            if ($paymentAddons->paystack_status !== 'inactive' && ($paymentAddons->paystack_public_key == 'paystack_public_key' || $paymentAddons->paystack_secret_key == 'paystack_secret_key' || $paymentAddons->paystack_public_key == '' || $paymentAddons->paystack_secret_key == '')) {
                return (object) [
                    'status' => true,
                    'message' => __('Paystack credentails not found'),
                    'description' => __('This may create a problem while making payment. Please fill up the credential to avoid any problem.'),
                    'route' => 'admin.paymentgateway',
                ];
            }

            if ($paymentAddons->mollie_status !== 'inactive' && ($paymentAddons->mollie_key == 'mollie_key' || $paymentAddons->mollie_key == '')) {
                return (object) [
                    'status' => true,
                    'message' => __('Mollie credentails not found'),
                    'description' => __('This may create a problem while making payment. Please fill up the credential to avoid any problem.'),
                    'route' => 'admin.paymentgateway',
                ];
            }

            if ($paymentAddons->instamojo_status !== 'inactive' && ($paymentAddons->instamojo_api_key == 'instamojo_api_key' || $paymentAddons->instamojo_auth_token == 'instamojo_auth_token' || $paymentAddons->instamojo_api_key == '' || $paymentAddons->instamojo_auth_token == '')) {
                return (object) [
                    'status' => true,
                    'message' => __('Instamojo credentails not found'),
                    'description' => __('This may create a problem while making payment. Please fill up the credential to avoid any problem.'),
                    'route' => 'admin.paymentgateway',
                ];
            }
        }

        if (Cache::has('bkashConfig') && Module::isEnabled('BkashPG')) {
            Cache::rememberForever('bkashConfig', function () {
                return (object) BkashPGModel::pluck('value', 'key')->toArray();
            });
        }
        if (Cache::has('bkashConfig') && $bkashAddons = Cache::get('bkashConfig')) {
            if ($bkashAddons->bkash_status !== 'inactive' && ($bkashAddons->bkash_key == 'bkash_key' || $bkashAddons->bkash_secret == 'bkash_secret' || $bkashAddons->bkash_username == 'bkash_username' || $bkashAddons->bkash_password == 'bkash_password' || $bkashAddons->bkash_key == '' || $bkashAddons->bkash_secret == '' || $bkashAddons->bkash_username == '' || $bkashAddons->bkash_password == '')) {
                return (object) [
                    'status' => true,
                    'message' => __('Bkash credentials not found'),
                    'description' => __('This may create a problem while making payment. Please fill up the credential to avoid any problem.'),
                    'route' => 'admin.basicpayment',
                ];
            }
        }

        if (Cache::has('cryptoConfig') && Module::isEnabled('CryptoPayment')) {
            Cache::rememberForever('cryptoConfig', function () {
                return (object) CryptoPG::pluck('value', 'key')->toArray();
            });
        }
        if (Cache::has('cryptoConfig') && $cryptoAddons = Cache::get('cryptoConfig')) {
            if ($cryptoAddons->crypto_status !== 'inactive' && ($cryptoAddons->crypto_token == 'crypto_token' || $cryptoAddons->crypto_token == '')) {
                return (object) [
                    'status' => true,
                    'message' => __('Coingate credentials not found'),
                    'description' => __('This may create a problem while making payment. Please fill up the credential to avoid any problem.'),
                    'route' => 'admin.basicpayment',
                ];
            }
        }

        if (Cache::has('mercadopagoConfig') && Module::isEnabled('MercadoPagoPG')) {
            Cache::rememberForever('mercadopagoConfig', function () {
                return (object) MercadoPagoPG::pluck('value', 'key')->toArray();
            });
        }
        if (Cache::has('mercadopagoConfig') && $mercadopagoConfig = Cache::get('mercadopagoConfig')) {
            if ($mercadopagoConfig->mercadopago_status !== 'inactive' && ($mercadopagoConfig->public_key == 'public_key' || $mercadopagoConfig->access_token == 'access_token' || $bkashAddons->public_key == '' || $bkashAddons->access_token == '')) {
                return (object) [
                    'status' => true,
                    'message' => __('Mercado Pago credentials not found'),
                    'description' => __('This may create a problem while making payment. Please fill up the credential to avoid any problem.'),
                    'route' => 'admin.basicpayment',
                ];
            }
        }

        return false;
    }
}

if (! function_exists('isRoute')) {
    function isRoute(string|array $route, ?string $returnValue = null)
    {
        if (is_array($route)) {
            foreach ($route as $value) {
                if (Route::is($value)) {
                    return is_null($returnValue) ? true : $returnValue;
                }
            }

            return false;
        }

        if (Route::is($route)) {
            return is_null($returnValue) ? true : $returnValue;
        }

        return false;
    }
}
// get default language
if (! function_exists('getDefaultLanguage')) {
    function getDefaultLanguage(): string
    {
        // cache default language
        $defaultLanguage = Cache::rememberForever('defaultLanguage', function () {
            try {
                return Language::where('is_default', 1)->first()->code;
            } catch (\Exception $e) {
                info($e->getMessage());

                return 'en';
            }
        });

        return $defaultLanguage;
    }
}

/**
 * Set the tab step for the form
 *
 * @param  string  $name  name of the tab session
 * @param  string  $step  current step of the tab
 * @return void
 */
if (! function_exists('setFormTabStep')) {
    function setFormTabStep(string $name, string $step): void
    {
        session()->flash($name, $step);
    }
}

/**
 * Get all countries from cache
 *
 * @return Collection all countries
 */
if (! function_exists('countries')) {
    function countries()
    {
        return Cache::rememberForever('countries', fn () => Country::all());
    }
}

if (! function_exists('instructorStatus')) {
    function instructorStatus()
    {
        return auth('web')->user()?->instructorInfo?->status;
    }
}
if (! function_exists('customCode')) {
    function customCode()
    {
        return Cache::rememberForever('customCode', function () {
            return CustomCode::select('css', 'header_javascript', 'javascript')->first();
        });
    }
}

/** Truncate string function */
if (! function_exists('truncate')) {
    function truncate($text, $limit = 60)
    {
        $text = $text ?? '';
        if (mb_strlen($text) > $limit) {
            return mb_substr($text, 0, $limit).'...';
        }

        return $text;
    }
}

/** Format date function */
if (! function_exists('formatDate')) {
    function formatDate($date, $format = 'd M, Y')
    {
        return Carbon::parse($date)->format($format);
    }
}
if (! function_exists('formatTime')) {
    function formatTime($date, $format = 'h:i a')
    {
        return Carbon::parse($date)->format($format);
    }
}
if (! function_exists('formattedDateTime')) {
    function formattedDateTime($datetime)
    {
        return formatDate($datetime).' - '.formatTime($datetime);
    }
}

/** Format minutes to hours */
if (! function_exists('minutesToHours')) {
    function minutesToHours($minutesToHours)
    {
        if ($minutesToHours === 0 || $minutesToHours === null) {
            return '--.--';
        }

        $hours = floor($minutesToHours / 60);
        $minutes = $minutesToHours % 60;

        return $hours.'h '.($minutes ? $minutes.'m' : '');
    }
}

/** Set enrollment ids in session */
if (! function_exists('setEnrollmentIdsInSession')) {
    function setEnrollmentIdsInSession()
    {
        if (auth('web')->check()) {
            $enrollmentsIds = Enrollment::where('user_id', userAuth()->id)->pluck('course_id')->toArray();
            session()->put('enrollments', $enrollmentsIds);

            return;
        }

        session()->put('enrollments', []);
    }
}
/** Set instructor course ids in session */
if (! function_exists('setInstructorCourseIdsInSession')) {
    function setInstructorCourseIdsInSession()
    {
        if (auth('web')->check() && userAuth()->role == 'instructor') {
            $enrollmentsIds = Course::where('instructor_id', userAuth()->id)->pluck('id')->toArray();
            session()->put('instructor_courses', $enrollmentsIds);

            return;
        }

        session()->put('instructor_courses', []);
    }
}

if (! function_exists('processText')) {
    function processText($text)
    {
        // Replace text within square brackets with a <span> tag
        $patternSquareBrackets = '/\[(.*?)\]/';
        $replacementSquareBrackets = '<span class="highlight">$1</span>';
        $text = preg_replace($patternSquareBrackets, $replacementSquareBrackets, $text);

        // Replace text within curly brackets with a <span> tag
        $patternCurlyBrackets = '/\{(.*?)\}/';
        $replacementCurlyBrackets = '<b>$1</b>';
        $text = preg_replace($patternCurlyBrackets, $replacementCurlyBrackets, $text);

        // Replace backslashes with <br> tags
        $patternBackslash = '/\\\\/';
        $replacementBackslash = '<br>';
        $text = preg_replace($patternBackslash, $replacementBackslash, $text);

        // Return the modified text
        return $text;
    }
}
function calculateReadingTime($content)
{
    // Average reading speed (words per minute)
    $readingSpeed = 200;

    // Strip HTML tags and count the words
    $wordCount = str_word_count(strip_tags($content));

    // Calculate the reading time in minutes
    $readingTime = ceil($wordCount / $readingSpeed);

    return $readingTime;
}

if (! function_exists('getTags')) {
    function getTags($jsonTag = [])
    {
        $tags = $jsonTag;
        $tags_string = '';
        foreach ($tags as $tag) {
            $tags_string .= $tag->value.',';
        }

        return $tags_string = rtrim($tags_string, ',');
    }
}
if (! function_exists('extractGoogleDriveVideoId')) {
    function extractGoogleDriveVideoId($url)
    {
        $googleDriveRegex = '/(?:https?:\/\/)?(?:www\.)?(?:drive\.google\.com\/(?:uc\?id=|file\/d\/|open\?id=)|youtu\.be\/)([\w-]{25,})[?=&#]*/';
        if (preg_match($googleDriveRegex, $url, $matches)) {
            return $matches[1];
        }

        return null;
    }
}

if (! function_exists('extractAndFilterImageSrc')) {
    function extractAndFilterImageSrc($string)
    {
        preg_match_all('/<img[^>]+src="([^">]+)"/i', $string, $matches);
        foreach (array_filter(array_map(function ($src) {
            $path = preg_replace('/^.*\/(uploads\/.*)$/', '$1', $src);

            return preg_match('/^uploads\/forum-images\/[^\/]+\.[a-zA-Z]{3,4}$/', $path) ? $path : null;
        }, $matches[1])) as $image) {
            $fullPath = public_path($image);
            if (File::exists($fullPath)) {
                File::delete($fullPath);
            }

        }
    }
}
if (! function_exists('replaceImageSources')) {
    function replaceImageSources($html)
    {
        $baseUrl = url('uploads/forum-images/');
        $pattern = '/<img\s+[^>]*src=["\']([^"\']+)["\'][^>]*>/i';

        $replacement = function ($matches) use ($baseUrl) {
            $existingSrc = $matches[1];
            $newSrc = $baseUrl.'/'.basename($existingSrc);

            return str_replace($existingSrc, $newSrc, $matches[0]);
        };
        $newHtml = preg_replace_callback($pattern, $replacement, $html);

        return $newHtml;
    }
}
if (! function_exists('adminSearchRouteList')) {
    /**
     * Per-request memoized list of admin routes that the navbar
     * search-box autocompletes against. Builds ~80 route() URLs on
     * first call; cached in a static so re-rendering partials or
     * widgets on the same request doesn't repeat the work.
     *
     * NOT cross-request cached — the URLs are locale-sensitive
     * (`route()` generates language-prefixed paths for some setups),
     * and the locale can change session-to-session.
     */
    function adminSearchRouteList(): object
    {
        static $_memo = null;
        if ($_memo !== null) {
            return $_memo;
        }
        $route_list = [
            (object) ['name' => __('Dashboard'), 'route' => route('admin.dashboard'), 'permission' => 'dashboard.view'],
            (object) ['name' => __('Courses'), 'route' => route('admin.courses.index'), 'permission' => 'course.management'],
            (object) ['name' => __('Course Categories'), 'route' => route('admin.course-category.index'), 'permission' => 'course.management'],
            (object) ['name' => __('Course languages'), 'route' => route('admin.course-language.index'), 'permission' => 'course.management'],
            (object) ['name' => __('Course levels'), 'route' => route('admin.course-level.index'), 'permission' => 'course.management'],
            (object) ['name' => __('Course Reviews'), 'route' => route('admin.course-review.index'), 'permission' => 'course.management'],
            (object) ['name' => __('Course Delete Requests'), 'route' => route('admin.course-delete-request.index'), 'permission' => 'course.management'],
            (object) ['name' => __('Certificate Builder'), 'route' => route('admin.certificate-builder.index'), 'permission' => 'course.certificate.management'],
            (object) ['name' => __('Badges'), 'route' => route('admin.badges.index'), 'permission' => 'badge.management'],
            (object) ['name' => __('Blog Categories'), 'route' => route('admin.blog-category.index'), 'permission' => 'blog.category.view'],
            (object) ['name' => __('Blog List'), 'route' => route('admin.blogs.index'), 'permission' => 'blog.view'],
            (object) ['name' => __('Blog Comments'), 'route' => route('admin.blog-comment.index'), 'permission' => 'blog.comment.view'],
            (object) ['name' => __('Order History'), 'route' => route('admin.orders'), 'permission' => 'order.management'],
            (object) ['name' => __('Pending Payment'), 'route' => route('admin.pending-orders'), 'permission' => 'order.management'],
            (object) ['name' => __('Coupon List'), 'route' => route('admin.coupon.index'), 'permission' => 'coupon.management'],
            (object) ['name' => __('Withdraw Method'), 'route' => route('admin.withdraw-method.index'), 'permission' => 'withdraw.management'],
            (object) ['name' => __('Withdraw list'), 'route' => route('admin.withdraw-list'), 'permission' => 'withdraw.management'],
            (object) ['name' => __('Instructor Request List'), 'route' => route('admin.instructor-request.index'), 'permission' => 'instructor.request.list'],
            (object) ['name' => __('Instructor Request Settings'), 'route' => route('admin.instructor-request-setting.index'), 'permission' => 'instructor.request.list'],
            (object) ['name' => __('All Students'), 'route' => route('admin.all-customers'), 'permission' => 'customer.view'],
            (object) ['name' => __('All Instructors'), 'route' => route('admin.all-instructors'), 'permission' => 'customer.view'],
            (object) ['name' => __('Active Users'), 'route' => route('admin.active-customers'), 'permission' => 'customer.view'],
            (object) ['name' => __('Non verified Users'), 'route' => route('admin.non-verified-customers'), 'permission' => 'customer.view'],
            (object) ['name' => __('Banned Users'), 'route' => route('admin.banned-customers'), 'permission' => 'customer.view'],
            (object) ['name' => __('Send bulk mail Users'), 'route' => route('admin.send-bulk-mail'), 'permission' => 'customer.view'],
            (object) ['name' => __('Countries'), 'route' => route('admin.country.index'), 'permission' => 'location.view'],
            (object) ['name' => __('Site Themes'), 'route' => route('admin.site-appearance.index'), 'permission' => 'appearance.management'],
            (object) ['name' => __('Section Setting'), 'route' => route('admin.section-setting.index'), 'permission' => 'appearance.management'],
            (object) ['name' => __('Site Colors'), 'route' => route('admin.site-color-setting.index'), 'permission' => 'appearance.management'],
            (object) ['name' => __('About Section'), 'route' => route('admin.about-section.index', ['code' => 'en']), 'permission' => 'section.management'],
            (object) ['name' => __('Featured Course Section'), 'route' => route('admin.featured-course-section.index'), 'permission' => 'section.management'],
            (object) ['name' => __('Newsletter Section'), 'route' => route('admin.newsletter-section.index'), 'permission' => 'section.management'],
            (object) ['name' => __('Featured Instructor'), 'route' => route('admin.featured-instructor-section.edit', ['featured_instructor_section' => 1, 'code' => 'en']), 'permission' => 'section.management'],
            (object) ['name' => __('Counter Section'), 'route' => route('admin.counter-section.index'), 'permission' => 'section.management'],
            (object) ['name' => __('Faq Section'), 'route' => route('admin.faq-section.index', ['code' => 'en']), 'permission' => 'section.management'],
            (object) ['name' => __('Certificate Section'), 'route' => route('admin.certificate-section.index', ['code' => 'en']), 'permission' => 'section.management'],
            (object) ['name' => __('Our Features Section'), 'route' => route('admin.our-features-section.index', ['code' => 'en']), 'permission' => 'section.management'],
            (object) ['name' => __('Banner Section'), 'route' => route('admin.banner-section.index'), 'permission' => 'section.management'],
            (object) ['name' => __('Contact Page Section'), 'route' => route('admin.contact-section.index'), 'permission' => 'section.management'],
            (object) ['name' => __('Brands'), 'route' => route('admin.brand.index'), 'permission' => 'brand.managemen'],
            (object) ['name' => __('Footer Setting'), 'route' => route('admin.footersetting.index'), 'permission' => 'footer.management'],
            (object) ['name' => __('Menu Builder'), 'route' => route('admin.menubuilder.index'), 'permission' => 'menu.view'],
            (object) ['name' => __('Page Builder'), 'route' => route('admin.page-builder.index'), 'permission' => 'page.management'],
            (object) ['name' => __('Page Template Builder'), 'route' => route('admin.page-template-builder.index'), 'permission' => 'page.management'],
            (object) ['name' => __('Social Links'), 'route' => route('admin.social-link.index'), 'permission' => 'social.link.management'],
            (object) ['name' => __('FAQS'), 'route' => route('admin.faq.index'), 'permission' => 'faq.view'],
            (object) ['name' => __('Subscriber List'), 'route' => route('admin.subscriber-list'), 'permission' => 'newsletter.view'],
            (object) ['name' => __('Subscriber Send bulk mail'), 'route' => route('admin.send-mail-to-newsletter'), 'permission' => 'newsletter.view'],
            (object) ['name' => __('Testimonial'), 'route' => route('admin.testimonial.index'), 'permission' => 'testimonial.view'],
            (object) ['name' => __('Contact Messages'), 'route' => route('admin.contact-messages'), 'permission' => 'contect.message.view'],
            (object) ['name' => __('Landing Page Messages'), 'route' => route('admin.landing-page.message'), 'permission' => 'landing-page.message.view'],
            (object) ['name' => __('General Settings'), 'route' => route('admin.general-setting'), 'permission' => 'setting.view', 'tab' => 'general_tab'],
            (object) ['name' => __('Logo & Favicon'), 'route' => route('admin.general-setting'), 'permission' => 'setting.view', 'tab' => 'logo_favicon_tab'],
            (object) ['name' => __('Video Watermark'), 'route' => route('admin.general-setting'), 'permission' => 'setting.view', 'tab' => 'watermark_tab'],
            (object) ['name' => __('Cookie Consent'), 'route' => route('admin.general-setting'), 'permission' => 'setting.view', 'tab' => 'cookie_consent_tab'],
            (object) ['name' => __('Breadcrumb image'), 'route' => route('admin.general-setting'), 'permission' => 'setting.view', 'tab' => 'breadcrump_img_tab'],
            (object) ['name' => __('Copyright Text'), 'route' => route('admin.general-setting'), 'permission' => 'setting.view', 'tab' => 'copyright_text_tab'],
            (object) ['name' => __('Maintenance Mode'), 'route' => route('admin.general-setting'), 'permission' => 'setting.view', 'tab' => 'mmaintenance_mode_tab'],
            (object) ['name' => __('Credential Settings'), 'route' => route('admin.credential-setting'), 'permission' => 'setting.view', 'tab' => 'google_recaptcha_tab'],
            (object) ['name' => __('Google reCaptcha'), 'route' => route('admin.credential-setting'), 'permission' => 'setting.view', 'tab' => 'google_recaptcha_tab'],
            (object) ['name' => __('Google Tag Manager'), 'route' => route('admin.credential-setting'), 'permission' => 'setting.view', 'tab' => 'google_tag_tab'],
            (object) ['name' => __('Wasabi Cloud Storage'), 'route' => route('admin.credential-setting'), 'permission' => 'setting.view', 'tab' => 'wasabi_tab'],
            (object) ['name' => __('AWS Cloud Storage'), 'route' => route('admin.credential-setting'), 'permission' => 'setting.view', 'tab' => 'aws_tab'],
            (object) ['name' => __('Google Analytic'), 'route' => route('admin.credential-setting'), 'permission' => 'setting.view', 'tab' => 'google_analytic_tab'],
            (object) ['name' => __('Facebook Pixel'), 'route' => route('admin.credential-setting'), 'permission' => 'setting.view', 'tab' => 'facebook_pixel_tab'],
            (object) ['name' => __('Social Login'), 'route' => route('admin.credential-setting'), 'permission' => 'setting.view', 'tab' => 'social_login_tab'],
            (object) ['name' => __('Tawk Chat'), 'route' => route('admin.credential-setting'), 'permission' => 'setting.view', 'tab' => 'tawk_chat_tab'],
            (object) ['name' => __('Email Configuration'), 'route' => route('admin.email-configuration'), 'permission' => 'setting.view', 'tab' => 'setting_tab'],
            (object) ['name' => __('Email Template'), 'route' => route('admin.email-configuration'), 'permission' => 'setting.view', 'tab' => 'email_template_tab'],
            (object) ['name' => __('SEO Setup'), 'route' => route('admin.seo-setting'), 'permission' => 'setting.view'],
            (object) [
                'name' => __('Custom CSS'),
                'route' => route('admin.custom-code', ['type' => 'css']),
                'permission' => 'setting.view',
            ],
            (object) [
                'name' => __('Custom JS'),
                'route' => route('admin.custom-code', ['type' => 'js']),
                'permission' => 'setting.view',
            ],
            (object) [
                'name' => __('Marketing Settings'),
                'route' => route('admin.marketing-setting'),
                'permission' => 'setting.view',
            ],
            (object) ['name' => __('Clear cache'), 'route' => route('admin.cache-clear'), 'permission' => 'setting.view'],
            (object) ['name' => __('Database Clear'), 'route' => route('admin.database-clear'), 'permission' => 'setting.view'],
            (object) ['name' => __('Zoom Health'), 'route' => route('admin.zoom-health.index'), 'permission' => 'setting.view'],
            (object) ['name' => __('Admin Commission'), 'route' => route('admin.commission-setting'), 'permission' => 'setting.view'],
            (object) ['name' => __('Manage Language'), 'route' => route('admin.languages.index'), 'permission' => 'language.view'],
            (object) ['name' => __('Payment Gateway'), 'route' => route('admin.basicpayment'), 'permission' => 'basic.payment.view'],
            (object) ['name' => __('Multi Currency'), 'route' => route('admin.currency.index'), 'permission' => 'currency.view'],
            (object) ['name' => __('Manage Admin'), 'route' => route('admin.admin.index'), 'permission' => 'admin.view'],
            (object) ['name' => __('Role & Permissions'), 'route' => route('admin.role.index'), 'permission' => 'role.view'],
        ];

        if (ThemeList::BUSINESS->value == DEFAULT_HOMEPAGE) {
            $route_list[] = (object) ['name' => __('Slider Section'), 'route' => route('admin.slider-section.index', ['code' => 'en']), 'permission' => 'section.management'];
        } else {
            $route_list[] = (object) ['name' => __('Hero Section'), 'route' => route('admin.hero-section.index', ['code' => 'en']), 'permission' => 'section.management'];
        }
        if (in_array(DEFAULT_HOMEPAGE, [ThemeList::MAIN->value, ThemeList::ONLINE->value, ThemeList::UNIVERSITY->value, ThemeList::LANGUAGE->value])) {
            $route_list[] = (object) ['name' => __('Counter Section'), 'route' => route('admin.counter-section.index'), 'permission' => 'section.management'];
        }

        usort($route_list, function ($a, $b) {
            return strcmp($a->name, $b->name);
        });

        return $_memo = (object) $route_list;
    }
}
// wasabi config setup
if (! function_exists('set_wasabi_config')) {
    function set_wasabi_config()
    {
        $wasabi_setting = Cache::get('setting');
        config(['filesystems.disks.wasabi.key' => $wasabi_setting?->wasabi_access_id]);
        config(['filesystems.disks.wasabi.secret' => $wasabi_setting?->wasabi_secret_key]);
        config(['filesystems.disks.wasabi.bucket' => $wasabi_setting?->wasabi_bucket]);
        config(['filesystems.disks.wasabi.region' => $wasabi_setting?->wasabi_region]);
    }
}
if (! function_exists('set_aws_config')) {
    function set_aws_config()
    {
        $aws_setting = Cache::get('setting');
        config(['filesystems.disks.aws.key' => $aws_setting?->aws_access_id]);
        config(['filesystems.disks.aws.secret' => $aws_setting?->aws_secret_key]);
        config(['filesystems.disks.aws.bucket' => $aws_setting?->aws_bucket]);
        config(['filesystems.disks.aws.region' => $aws_setting?->aws_region]);
        config(['filesystems.disks.aws.url' => "https://{$aws_setting?->aws_bucket}.s3.amazonaws.com/"]);
    }
}
if (! function_exists('generateUniqueSlug')) {
    /**
     * Generate a unique slug for a model based on an initial base slug.
     *
     * @param  string  $model  The model class to check for existing slugs (e.g., Course::class).
     * @param  string  $title  The title to convert to a base slug.
     * @return string A unique slug string that can be safely used in the model.
     */
    function generateUniqueSlug($model, $title): string
    {
        $baseSlug = Str::slug($title, '-');

        $slug = $baseSlug;
        $counter = 1;

        while ($model::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter++;
        }

        return $slug;
    }
}
if (! function_exists('convertMinutesToHoursAndMinutes')) {
    function convertMinutesToHoursAndMinutes($minutes)
    {
        if ($minutes <= 0) {
            return '0m';
        }

        return $minutes < 60 ? "{$minutes}m" : floor($minutes / 60).'h'.($minutes % 60 ? ' '.$minutes % 60 .'m' : '');
    }
}
if (! function_exists('generateVideoEmbedUrl')) {
    /**
     * Generates an embed URL for video platforms.
     *
     * @param  string  $storage  The storage platform ('upload','youtube','vimeo','external_link','google_drive','iframe','wasabi','aws').
     * @param  string  $file_type  The type of file (video','audio','pdf','txt','docx','iframe','image','file','other).
     * @param  string  $url  The video URL.
     * @return string|null The embed URL or null if not found.
     */
    function generateVideoEmbedUrl($url, $storage, $file_type = 'video')
    {
        if ($file_type !== 'video') {
            return asset($url);
        }
        if ($storage == 'google_drive') {
            if (preg_match('/(?:https?:\/\/)?(?:www\.)?(?:drive\.google\.com\/(?:uc\?id=|file\/d\/|open\?id=)|youtu\.be\/)([\w-]{25,})[?=&#]*/', $url, $match)) {
                return 'https://drive.google.com/file/d/'.$match[1].'/preview';
            }

            return null;
        }
        if ($storage == 'youtube') {
            if (preg_match('/(?:youtube\.com\/(?:watch\?v=|v\/)|youtu\.be\/)([\w-]{11})/', $url, $match)) {
                return 'https://www.youtube.com/embed/'.$match[1].'?rel=0';
            }

            return null;
        }
        if ($storage == 'vimeo') {
            if (preg_match('/(?:vimeo\.com\/)(\d{8,})/', $url, $match)) {
                return 'https://player.vimeo.com/video/'.$match[1];
            }

            return null;
        }
        if (in_array($storage, ['wasabi', 'aws'])) {
            return Storage::disk($storage)->temporaryUrl($url, now()->addSeconds(30));
        }

        return asset($url);
    }

}
if (! function_exists('apiCurrency')) {
    // calculate currency
    function apiCurrency($price, $currency_code = null)
    {
        $currency = allCurrencies()->where('currency_code', $currency_code)->first();
        if (! $currency) {
            $currency = allCurrencies()->where('is_default', 'yes')->first();
        }

        $currency_icon = $currency->currency_icon;
        $currency_rate = $currency->currency_rate;
        $currency_position = $currency->currency_position;

        $price = $price * $currency_rate;
        $price = number_format($price, 2, '.', ',');

        if ($currency_position == 'before_price') {
            $price = $currency_icon.$price;
        } elseif ($currency_position == 'before_price_with_space') {
            $price = $currency_icon.' '.$price;
        } elseif ($currency_position == 'after_price') {
            $price = $price.$currency_icon;
        } elseif ($currency_position == 'after_price_with_space') {
            $price = $price.' '.$currency_icon;
        } else {
            $price = $currency_icon.$price;
        }

        return $price;
    }
}

if (! function_exists('sessionCartToDatabase')) {
    /**
     * Transfers items from the session cart to the authenticated user's database cart.
     *
     * @param  \App\Models\User  $user  The authenticated user.
     */
    function sessionCartToDatabase(): void
    {
        if (Cart::content()->count() > 0 && auth()->check()) {
            $user = userAuth();
            $carts = Cart::content();
            foreach ($carts as $item) {
                $course = Course::active()->find($item->id);
                if ($course && ! isOwnCourse($user, $course) && ! hasCourseInPurchased($user, $course)) {
                    $user->carts()->create(['course_id' => $item->id,'batch_id'=>$item->options->batch_id??""]);
                }
            }
            Cart::destroy();
        }
    }
}
if (! function_exists('isOwnCourse')) {
    function isOwnCourse($user, $course)
    {
        return $course->instructor_id == $user->id;
    }
}
if (! function_exists('hasCourseInPurchased')) {
    function hasCourseInPurchased($user, $course)
    {
        return $user->enrollments()->where('course_id', $course->id)->exists();
    }
}
if (! function_exists('hasCourseInCart')) {
    function hasCourseInCart($user, $course)
    {
        return $user->carts()->where('course_id', $course->id)->exists();
    }
}

if (! function_exists('pre')) {
    function pre($data)
    {
        echo '<pre>';
        print_r($data);
    }

    if (! function_exists('checkPermission')) {
        function checkPermission($pageName, $methodName = null)
        {
            // SECURITY (2026-06-01) — a REAL coach is a top-level account
            // (coach_id IS NULL). A coach-staff member always has coach_id set.
            // Requiring empty(coach_id) here means a staff account whose role
            // string is somehow 'instructor' can NOT short-circuit to
            // full-access; it falls through to the per-slug CoachStaff check.
            if(auth('web')->user()->role=='instructor' && empty(auth('web')->user()->coach_id)){
                return 1;
            }

            // 2026-07-04 FIX — the required permission slug is derived from the
            // ($pageName, controller-action) pair, NOT from the request URL's
            // last segment. The old URL-segment logic silently denied a permitted
            // staff member whenever a module's permission slug differed from its
            // route path (e.g. slug `coach-coupons` on URL `/instructor/coupons`
            // built the bogus slug `coach-coupons-coupons`, so the coach panel
            // showed a "page not available" error even though the staff HELD the
            // permission). Mapping action→suffix makes it route-independent.
            $logednuserid = auth('web')->user()->id;
            $user = CoachStaff::find($logednuserid);
            if (! $user) {
                return 0;
            }
            $slugs = $user->permissions->pluck('slug')->all();

            // index / listing / access (no action, or a read action) → the BARE
            // resource slug. Write actions map to their granular suffix.
            $need = $pageName;
            switch ($methodName) {
                case 'create':
                case 'store':
                    $need = $pageName . '-create';
                    break;
                case 'edit':
                case 'update':
                    $need = $pageName . '-edit';
                    break;
                case 'destroy':
                case 'delete':
                    $need = $pageName . '-delete';
                    break;
                case 'show':
                    $need = $pageName . '-show';
                    break;
                // index / null / any custom read method → bare $pageName
            }

            return in_array($need, $slugs, true) ? 1 : 0;
        }
    }

    if (! function_exists('checkPermissionView')) {
        function checkPermissionView($customSlug = null)
        {
             if(auth('web')->user()->role=='instructor' && empty(auth('web')->user()->coach_id)){
                return 1;
            }
             if(auth('web')->user()->role=='student'){
                // 2026-06-16 audit L7 — fail CLOSED. Students hold no coach-staff
                // permissions; returning 1 rendered coach-panel action buttons if
                // a student ever reached a coach Blade. Routes are middleware-gated
                // so this was defense-in-depth, but the helper must deny.
                return 0;
            }
            //  return 1;
             $logednuserid = auth('web')->user()->id;
            $user = CoachStaff::find($logednuserid); 
            $userPermissions = $user->permissions->toArray();
            $flag = 0;
            if (! empty($userPermissions)) {
                foreach ($userPermissions as $key => $value) {
                    if ($value['slug'] == $customSlug) {
                        $flag = 1;
                    }
                }
            }

            return $flag;
        }
    }

}


// ─────────────────────────────────────────────────────────────────────
// P1-4 (2026-05-29) — CSP nonce helper.
//
// ContentSecurityPolicy middleware generates a 128-bit nonce per request
// and stamps it onto the request attributes. Views need a short way to
// render `<script nonce="...">` and `<style nonce="...">` tags so they
// survive a tightened policy. Returns '' if no request is bound
// (e.g. CLI / artisan tinker) — callers should treat empty as "no nonce".
// ─────────────────────────────────────────────────────────────────────
if (! function_exists('brandedUrl')) {
    /**
     * Tenant-safe URL: when $coachId has a VERIFIED custom domain, rewrite the
     * platform host in $value (a URL or a block of HTML) to that coach's host
     * so white-label emails never link a coach's student back to the platform.
     * Only the platform host is rewritten (external links untouched). No coach
     * domain / no coach -> returned unchanged.
     */
    function brandedUrl(?string $value, ?int $coachId): ?string
    {
        if (! $coachId || $value === null || $value === '') {
            return $value;
        }
        try {
            $host = \App\Models\CoachDomain::primaryHostFor((int) $coachId);
            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            if (! $host || ! $appHost) {
                return $value;
            }
            return preg_replace('#//' . preg_quote($appHost, '#') . '(?=[/:"\'\s]|$)#', '//' . $host, $value);
        } catch (\Throwable $e) {
            return $value;
        }
    }
}

if (! function_exists('csp_nonce')) {
    function csp_nonce(): string
    {
        try {
            $req = app('request');
            return (string) ($req?->attributes->get('csp_nonce') ?? '');
        } catch (\Throwable $e) {
            return '';
        }
    }
}

if (! function_exists('safe_url')) {
    /**
     * F6 (audit 2026-06-26) — sanitize a coach-supplied link URL. Blade {{ }}
     * escapes HTML but NOT the scheme, so javascript:/data:/vbscript: in a
     * coach's cta_url/btn_url/social link is a clickable XSS sink. Allow only
     * http(s), mailto, tel, anchors (#...) and relative/absolute paths; anything
     * else returns the fallback (default '#').
     */
    function safe_url(?string $url, string $fallback = '#'): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return $fallback;
        }
        // Relative path, anchor or protocol-relative — safe.
        if (preg_match('#^(/|\./|\.\./|\#|\?)#', $url) || str_starts_with($url, '//')) {
            return $url;
        }
        // Has a scheme? Only allow a safe allowlist.
        if (preg_match('#^([a-zA-Z][a-zA-Z0-9+.\-]*):#', $url, $m)) {
            $scheme = strtolower($m[1]);
            return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true) ? $url : $fallback;
        }
        // No scheme (e.g. "example.com/path" or "page") — treat as a path; safe.
        return $url;
    }
}

if (! function_exists('safe_embed_url')) {
    /**
     * F7 (audit 2026-06-26) — only allow a coach map-embed iframe src from a
     * known map provider over https; anything else returns '' (no iframe), so a
     * coach cannot frame an arbitrary origin for phishing on their branded site.
     */
    function safe_embed_url(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '' || ! preg_match('#^https://#i', $url)) {
            return '';
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $allowed = ['www.google.com', 'google.com', 'maps.google.com', 'www.openstreetmap.org',
            'openstreetmap.org', 'www.bing.com', 'maps.app.goo.gl'];
        foreach ($allowed as $h) {
            if ($host === $h || str_ends_with($host, '.' . $h)) {
                return $url;
            }
        }
        return '';
    }
}

if (! function_exists('offlineEnabledMethods')) {
    /**
     * The offline payment methods the CURRENT coach accepts (per-coach config),
     * as [key => [label, icon]] for the acting coach/staff. Used by every offline
     * payment form so a coach only sees the methods they've enabled. Defaults to
     * all methods when unconfigured. Tenant-safe (keyed to the acting coach).
     */
    function offlineEnabledMethods(): array
    {
        $u = userAuth();
        $coachId = $u ? ($u->role === 'instructor' ? (int) $u->id : (int) $u->coach_id) : 0;
        $keys = $coachId > 0
            ? \App\Models\OfflinePayment::enabledMethodsForCoach($coachId)
            : \App\Models\OfflinePayment::METHODS;

        $out = [];
        foreach ($keys as $k) {
            $out[$k] = \App\Models\OfflinePayment::METHOD_META[$k] ?? [ucfirst($k), 'fa-money-bill'];
        }
        return $out;
    }
}

if (! function_exists('panelModuleTitle')) {
    /**
     * Descriptive browser-tab module name for the Coach / Staff / Student
     * dashboards (New Changes for UI #3). Prefers an explicit title (a
     * controller's $metadta['title']); otherwise derives a friendly label
     * from the current route path so every page gets a meaningful <title>
     * even when the controller didn't set one. The layout appends the brand
     * as "Module | Brand". Works on direct URL access + refresh because it
     * reads the resolved request path (no client-side JS needed).
     */
    function panelModuleTitle(?string $explicit = null): string
    {
        if (filled($explicit)) {
            return $explicit;
        }

        // Friendly module names keyed by URL path segment. Keep in sync with
        // the sidebar modules; unmapped segments fall back to a Title Case of
        // the segment so new modules still read reasonably.
        $map = [
            'analytics'            => 'Analytics',
            'live-classes'         => 'Live Classes',
            'instant-meetings'     => 'Instant Meeting 1:1',
            'courses'              => 'Courses',
            'course-batches'       => 'Course Batches',
            'coach-orders'         => 'Orders',
            'coach-students'       => 'Students',
            'landing-page-enquiry' => 'Enquiries',
            'pricing-enquiries'    => 'Enquiries',
            'trainers'             => 'Trainers',
            'trial-sessions'       => 'Trial Sessions',
            'announcements'        => 'Announcements',
            'coupons'              => 'Coupons',
            'blogs'                => 'Blog',
            'setting'              => 'Settings',
            'brand-settings'       => 'Settings',
            'website-builder'      => 'Website Builder',
            'web-page'             => 'Website Builder',
            'coach-staff'          => 'Staff',
            'coach-staff-role'     => 'Staff Roles',
            'coach-staff-permission' => 'Staff Permissions',
            'staff-role'           => 'Staff Roles',
            'staff-permission'     => 'Staff Permissions',
            'teacher-batches'      => 'Teacher Batches',
            'payout'               => 'Payouts',
            'certificate'          => 'Certificates',
            'certificate-builder'  => 'Certificate Builder',
            'membership'           => 'Membership',
            'my-plan'              => 'Plan & Billing',
            'subscription-histories' => 'Subscription History',
            'referral'             => 'Referrals',
            'wishlist'             => 'Wishlist',
            'reviews'              => 'Reviews',
            'enrolled-courses'     => 'My Courses',
            'my-certificates'      => 'My Certificates',
            'profile'              => 'Profile',
            'orders'               => 'Orders',
            'fees'                 => 'Fees',
            'offline-payments'     => 'Offline Payments',
            'attendance'           => 'Attendance',
            'tax'                  => 'Tax',
            'tax-settings'         => 'Tax Settings',
            'zoom-setting'         => 'Zoom Settings',
            'youtube-setting'      => 'YouTube Settings',
            'payment-gateways'     => 'Payment Gateways',
            'email-templates'      => 'Email Templates',
        ];

        $segments = array_values(array_filter(explode('/', trim((string) request()->path(), '/'))));
        if (empty($segments)) {
            return 'Dashboard';
        }

        $panel   = $segments[0];
        $isPanel = in_array($panel, ['instructor', 'student'], true);

        // For panel pages the module is the first mapped segment after the
        // prefix; for root-level pages (e.g. /referral) the first segment
        // itself is the module. First mapped segment wins.
        $candidates = $isPanel ? array_slice($segments, 1) : $segments;
        foreach ($candidates as $seg) {
            if (! empty($map[$seg])) {
                return $map[$seg];
            }
        }

        // Unmapped but a real module segment exists → Title-Case it so brand new
        // modules still get a meaningful tab title instead of the generic
        // "Coach Dashboard"/"Student Dashboard" (the reported bug). 'dashboard'
        // is the landing itself, so it keeps the panel label below.
        $moduleSeg = $isPanel ? ($segments[1] ?? null) : ($segments[0] ?? null);
        if (! empty($moduleSeg) && $moduleSeg !== 'dashboard') {
            return \Illuminate\Support\Str::title(str_replace('-', ' ', $moduleSeg));
        }

        if ($panel === 'student') return 'Student Dashboard';
        if ($panel === 'instructor') return 'Coach Dashboard';
        return 'Dashboard';
    }
}

if (! function_exists('adminMenuCounts')) {
    /**
     * Counts shown as badges in the Super-Admin sidebar (2026-07-20, Phase 5).
     *
     * ONE cached call for the whole menu. This matters: the sidebar renders on
     * every one of the ~120 admin pages, and the module partials were each
     * running their own uncached `->count()` inline in Blade — Order and
     * Subscription ran the *same* pending-orders query twice per page load.
     *
     * The definitions deliberately mirror the dashboard's cached aggregates
     * (coaches = users.role 'instructor', etc.) so a badge can never disagree
     * with the KPI tile for the same thing.
     *
     * Every lookup is wrapped so a missing table/model degrades to no badge
     * rather than 500-ing the layout on every page.
     */
    function adminMenuCounts(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('admin.menu.counts', 60, function () {
            $safe = function (callable $fn) {
                try {
                    return (int) $fn();
                } catch (\Throwable $e) {
                    return null;
                }
            };

            return [
                'coaches'          => $safe(fn () => \App\Models\User::where('role', 'instructor')->count()),
                'students'         => $safe(fn () => \App\Models\User::where('role', 'student')->count()),
                'domains_active'   => $safe(fn () => \App\Models\CoachDomain::where('status', 'active')->count()),
                // Same model + status the dashboard's Referrals panel reads, so a
                // badge can never show a different number than the panel does.
                'referrals'        => $safe(fn () => \App\Models\Referral::count()),
                'referrals_review' => $safe(fn () => \App\Models\Referral::where('status', 'pending')->count()),
                'pending_orders'   => $safe(fn () => \Modules\Order\app\Models\Order::where('payment_status', 'pending')->count()),
                'booking_enq'      => $safe(fn () => \DB::table('coach_pricing_enquiries')->count()),
            ];
        });
    }
}

if (! function_exists('adminMenuBadge')) {
    /**
     * Render a sidebar count badge, or nothing when there is nothing to say.
     *
     * A badge that reads "0" is noise — the operator only cares that a number
     * exists. `$alert` renders it in the danger colour for queues that need
     * action (pending orders, requests awaiting review).
     */
    function adminMenuBadge(?int $count, bool $alert = false): string
    {
        if ($count === null || $count <= 0) {
            return '';
        }

        return '<span class="mbs-cb' . ($alert ? ' al' : '') . '">'
            . e(number_format($count)) . '</span>';
    }
}
