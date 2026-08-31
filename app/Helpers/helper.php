<?php

use App\Exceptions\AccessPermissionDeniedException;
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
use Modules\Currency\app\Models\MultiCurrency;
use Modules\GlobalSetting\app\Models\CustomCode;
use Modules\GlobalSetting\app\Models\Setting;
use Modules\Language\app\Models\Language;
use Modules\Location\app\Models\Country;
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
/* LMS removal phase 2 (2026-08-27) — removed getSocialLinks() (SocialLink
 * module), coachCommerceUrl() (white-label /cart + /checkout URL builder)
 * and orderInvoiceBrand() (per-coach branding on a course order invoice). */

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
        // LMS removal phase 2 (2026-08-31) — dropped a dozen aliases for
        // admin.membership-plans.*/user-memberships.*/referral-commissions.*/
        // referrals.*/coach-landing-pages.*/zoom-health.*. None of those
        // routes exist any more.
        static $aliases = [
            'admin.dashboard'                                  => 'Dashboard',
            'admin.edit-profile'                               => 'Edit Profile',
            'admin.settings'                                   => 'Settings',
            'admin.2fa.setup'                                  => 'Two-Factor Authentication',
            'admin.2fa.challenge'                              => 'Two-Factor Verification',
            'admin.role.index'                                 => 'Roles',
            'admin.admin.index'                                => 'Admins',
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
/* LMS removal phase 2 (2026-08-27) — also removed checkCrentials(), the
 * admin credential-health banner. Most of its checks were for the payment
 * gateways (Stripe/Razorpay/PayPal/bKash/Coingate/MercadoPago) configured
 * by the deleted BasicPayment + gateway modules, and it linked to
 * admin.basicpayment. Also removed setEnrollmentIdsInSession() and
 * setInstructorCourseIdsInSession(), which the root layout called on every
 * page render. */


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
     * search-box autocompletes against.
     *
     * NOT cross-request cached — the URLs are locale-sensitive
     * (`route()` generates language-prefixed paths for some setups),
     * and the locale can change session-to-session.
     *
     * LMS removal phase 2 (2026-08-27) — this listed ~80 entries, almost all
     * of them storefront/LMS admin screens: courses and their taxonomy,
     * orders, coupons, customers, instructors, instructor requests, blogs,
     * FAQs, testimonials, badges, certificates, newsletters, withdrawals,
     * memberships, referrals, coach commissions, custom domains, the theme
     * studio, the payment-gateway modules and every marketing section of the
     * homepage builder. All of those routes are gone. What is left is the
     * super-admin surface that actually survives, plus payroll.
     */
    function adminSearchRouteList(): object
    {
        static $_memo = null;
        if ($_memo !== null) {
            return $_memo;
        }

        $route_list = [
            (object) ['name' => __('Dashboard'),           'route' => route('admin.dashboard'),         'permission' => null],
            (object) ['name' => __('Payroll Dashboard'),   'route' => route('admin.payroll.dashboard'), 'permission' => null],
            (object) ['name' => __('Payroll Runs'),        'route' => route('admin.payroll.index'),     'permission' => null],
            (object) ['name' => __('Activity Logs'),       'route' => route('admin.activity-logs'),     'permission' => null],
            (object) ['name' => __('Admin List'),          'route' => route('admin.admin.index'),       'permission' => 'admin.view'],
            (object) ['name' => __('Role & Permissions'),  'route' => route('admin.role.index'),        'permission' => 'role.view'],
            (object) ['name' => __('General Setting'),     'route' => route('admin.general-setting'),   'permission' => 'setting.management'],
            (object) ['name' => __('Email Configuration'), 'route' => route('admin.email-configuration'), 'permission' => 'setting.management'],
            (object) ['name' => __('SEO Setting'),         'route' => route('admin.seo-setting'),       'permission' => 'setting.management'],
            (object) ['name' => __('Marketing Setting'),   'route' => route('admin.marketing-setting'), 'permission' => 'setting.management'],
            (object) ['name' => __('Credential Setting'),  'route' => route('admin.credential-setting'), 'permission' => 'setting.management'],
            (object) ['name' => __('Custom Code'),         'route' => route('admin.custom-code'),       'permission' => 'setting.management'],
            (object) ['name' => __('Languages'),           'route' => route('admin.languages.index'),   'permission' => 'language.management'],
            (object) ['name' => __('Currencies'),          'route' => route('admin.currency.index'),    'permission' => 'currency.management'],
            (object) ['name' => __('Countries'),           'route' => route('admin.country.index'),     'permission' => 'location.management'],
            (object) ['name' => __('Profile'),             'route' => route('admin.edit-profile'),      'permission' => null],
        ];

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

if (! function_exists('pre')) {
    function pre($data)
    {
        echo '<pre>';
        print_r($data);
    }
}

/* LMS removal phase 2 (2026-08-27) — removed from this file:
 *   sessionCartToDatabase()  — merged the guest cart into the DB on login.
 *   isOwnCourse() / hasCourseInPurchased() / hasCourseInCart().
 *   checkPermission() / checkPermissionView() — the coach-staff permission
 *     gate. It read CoachStaff->permissions slugs and paired with the
 *     CoachPermission middleware and the Staff Roles screens, all deleted.
 *   brandedUrl() — rewrote the platform host in outbound mail to a coach's
 *     verified custom domain.
 *   offlineEnabledMethods() — the coach's enabled offline payment methods.
 */

// ─────────────────────────────────────────────────────────────────────
// P1-4 (2026-05-29) — CSP nonce helper.
//
// ContentSecurityPolicy middleware generates a 128-bit nonce per request
// and stamps it onto the request attributes. Views need a short way to
// render `<script nonce="...">` and `<style nonce="...">` tags so they
// survive a tightened policy. Returns '' if no request is bound
// (e.g. CLI / artisan tinker) — callers should treat empty as "no nonce".
// ─────────────────────────────────────────────────────────────────────
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
        // LMS removal phase 2 (2026-08-27) — the map was ~45 LMS/coach
        // segments (courses, batches, live classes, orders, coupons, payouts,
        // certificates, website builder, staff roles, trial sessions...).
        // Replaced with the HR & payroll surface; unmapped segments still fall
        // back to a Title Case of the segment.
        $map = [
            'overview'           => 'Overview',
            'employees'          => 'Employees',
            'departments'        => 'Departments',
            'attendance'         => 'Attendance',
            'leave'              => 'Leave',
            'payroll'            => 'Payroll',
            'payslips'           => 'Payslips',
            'salary-structures'  => 'Salary Structures',
            'companies'          => 'Companies',
            'setting'            => 'Settings',
            'profile'            => 'Profile',
            'notifications'      => 'Notifications',
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

            // LMS removal phase 2 (2026-08-27) — dropped domains_active,
            // referrals, referrals_review, pending_orders and booking_enq. Each
            // counted a table this phase drops. Coaches/students stay: they are
            // just users.role, and read as HR users vs employees now.
            return [
                'coaches'  => $safe(fn () => \App\Models\User::where('role', 'instructor')->count()),
                'students' => $safe(fn () => \App\Models\User::where('role', 'student')->count()),
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
