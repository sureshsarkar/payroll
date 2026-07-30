<?php

namespace Modules\GlobalSetting\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Modules\GlobalSetting\app\Enums\AllTimeZoneEnum;
use Modules\GlobalSetting\app\Models\CustomCode;
use Modules\GlobalSetting\app\Models\CustomPagination;
use Modules\GlobalSetting\app\Models\MarketingSetting;
use Modules\GlobalSetting\app\Models\SeoSetting;
use Modules\GlobalSetting\app\Models\Setting;
use ZipArchive;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;

class GlobalSettingController extends Controller
{
    protected $cachedSetting;

    public function __construct()
    {
        $this->cachedSetting = Cache::get('setting');
    }

    public function general_setting()
    {
        checkAdminHasPermissionAndThrowException('setting.view');
        $custom_paginations = CustomPagination::all();
        $all_timezones = AllTimeZoneEnum::getAll();
        
        return view('globalsetting::settings.index', compact('custom_paginations', 'all_timezones'));
    }

    public function commission_setting()
    {
        checkAdminHasPermissionAndThrowException('setting.view');
        return view('globalsetting::commission_setting');
    }

    function update_commission(Request $request)
    {
        // FT-IDOR-28 fix (2026-05-28) — CRITICAL: was completely
        // ungated AND lacked bounds.
        //
        // commission_rate is the platform-wide percentage taken
        // from every coach's course sale (Order::commission_rate
        // is read at the moment of order creation; later sales use
        // whatever the global Setting says now).
        //
        // Pre-fix: any logged-in admin (Content Editor, etc.) could
        // POST commission_rate=999 (every sale strips ~10x its
        // value from the coach — the platform owes them money)
        // OR commission_rate=-100 (every sale ADDS money to the
        // coach's wallet on top of the sale price — net drain
        // from the platform).
        //
        // Now requires `setting.update` (the slug every sibling
        // update_* method on this controller uses) and clamps to
        // 0..99% which is the realistic SaaS marketplace range.
        // A 100% commission would zero out coach wallets on every
        // sale and is excluded by `lt:100`.
        checkAdminHasPermissionAndThrowException('setting.update');

        // FT-VAL-6 — bound commission_rate.
        $rules = [
            'commission_rate' => 'required|numeric|min:0|lt:100',
        ];

        $messages = [
            'commission_rate.required' => __('Commission is required'),
            'commission_rate.numeric'  => __('Commission is invalid'),
            'commission_rate.min'      => __('Commission cannot be negative'),
            'commission_rate.lt'       => __('Commission must be less than 100%'),
        ];

        $this->validate($request, $rules, $messages);

        Setting::updateOrCreate(['key' => 'commission_rate'], ['value' => $request->commission_rate]);

        $notification = __('Update Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];
        $this->put_setting_cache();

        return redirect()->back()->with($notification);
    }

    public function update_general_setting(Request $request)
    {
        checkAdminHasPermissionAndThrowException('setting.update');
        $request->validate([
            'app_name'       => 'required',
            'timezone'       => 'required',
            'is_queable'     => 'required|in:active,inactive',
            'live_mail_send' => 'required',
        ], [
            'app_name'                => __('App name is required'),
            'timezone'                => __('Timezone is required'),
            'is_queable'              => __('Queue is required'),
            'is_queable.in'           => __('Queue is invalid'),
            'live_mail_send.required' => __('Live class mail send time required'),
        ]);

        Setting::where('key', 'app_name')->update(['value' => $request->app_name]);
        Setting::where('key', 'years_of_exprience')->update(['value' => $request->years_of_exprience]);
        Setting::where('key', 'satisfied_clients')->update(['value' => $request->satisfied_clients]);
        Setting::where('key', 'countries_reached')->update(['value' => $request->countries_reached]);
        Setting::where('key', 'classes_conducted')->update(['value' => $request->classes_conducted]);
        Setting::where('key', 'timezone')->update(['value' => $request->timezone]);
        Setting::where('key', 'is_queable')->update(['value' => $request->is_queable]);
        Setting::where('key', 'site_address')->update(['value' => $request->site_address]);
        Setting::where('key', 'site_email')->update(['value' => $request->site_email]);
        Setting::where('key', 'header_topbar_status')->update(['value' => $request->header_topbar_status]);
        Setting::where('key', 'header_social_status')->update(['value' => $request->header_social_status]);
        Setting::where('key', 'cursor_dot_status')->update(['value' => $request?->cursor_dot_status]);
        Setting::where('key', 'preloader_status')->update(['value' => $request->preloader_status]);
        Setting::where('key', 'live_mail_send')->update(['value' => $request->live_mail_send]);

        $this->put_setting_cache();
        Cache::forget('corn_working');

        $notification = __('Update Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function update_logo_favicon(Request $request)
    {
        checkAdminHasPermissionAndThrowException('setting.update');

        // FT-UPLOAD-3 (GlobalSetting, 2026-05-28) — was no validate()
        // call. The file_upload helper rejects svg/php downstream,
        // but validating up-front gives a friendly error and rejects
        // oversize / non-image payloads before the helper runs.
        $request->validate([
            'logo'      => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'favicon'   => ['nullable', 'image', 'mimes:png,ico,webp', 'max:512'],
            'preloader' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
        ]);

        if ($request->file('logo')) {
            $file_name = file_upload($request->logo, 'uploads/custom-images/', $this->cachedSetting?->logo);
            Setting::where('key', 'logo')->update(['value' => $file_name]);
        }

        if ($request->file('favicon')) {
            $file_name = file_upload($request->favicon, 'uploads/custom-images/', $this->cachedSetting?->favicon);
            Setting::where('key', 'favicon')->update(['value' => $file_name]);
        }
        if ($request->file('preloader')) {
            $file_name = file_upload($request->preloader, 'uploads/custom-images/');
            Setting::where('key', 'preloader')->update(['value' => $file_name]);
        }

        $this->put_setting_cache();

        $notification = __('Update Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }
    public function update_video_watermark(Request $request)
    {
        checkAdminHasPermissionAndThrowException('setting.update');
        // FT-UPLOAD-3 (GlobalSetting watermark, 2026-05-28) — added
        // `watermark_img` rule. The helper still rejects unsafe
        // formats, but validating upstream gives a friendly error.
        $request->validate([
            'opacity'          => 'required',
            'position'         => 'required',
            'max_width'        => 'required',
            'watermark_status' => 'nullable',
            'watermark_img'    => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
        ], [
            'opacity.required'   => __('Opacity is required'),
            'position.required'  => __('Position is required'),
            'max_width.required' => __('Max width is required'),
        ]);

        if ($request->file('watermark_img')) {
            $file_name = file_upload($request->watermark_img, 'uploads/custom-images/', $this->cachedSetting?->watermark_img);
            Setting::where('key', 'watermark_img')->update(['value' => $file_name]);
        }
        Setting::where('key', 'opacity')->update(['value' => $request->opacity]);
        Setting::where('key', 'position')->update(['value' => $request->position]);
        Setting::where('key', 'max_width')->update(['value' => $request->max_width]);
        Setting::where('key', 'watermark_status')->update(['value' => $request->watermark_status]);

        $this->put_setting_cache();

        $notification = __('Update Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function update_cookie_consent(Request $request)
    {
        checkAdminHasPermissionAndThrowException('setting.update');
        $request->validate([
            'cookie_status'    => 'required',
            'border'           => 'required',
            'corners'          => 'required',
            'background_color' => 'required',
            'text_color'       => 'required',
            'border_color'     => 'required',
            'btn_bg_color'     => 'required',
            'btn_text_color'   => 'required',
            'link_text'        => 'required',
            'btn_text'         => 'required',
            'message'          => 'required',
            'link'             => 'required',
        ], [
            'cookie_status.required'    => __('Status is required'),
            'border.required'           => __('Border is required'),
            'corners.required'          => __('Corner is required'),
            'background_color.required' => __('Background color is required'),
            'text_color.required'       => __('Text color is required'),
            'border_color.required'     => __('Border Color is required'),
            'btn_bg_color.required'     => __('Button color is required'),
            'btn_text_color.required'   => __('Button text color is required'),
            'link_text.required'        => __('Link text is required'),
            'link.required'             => __('Link is required'),
            'btn_text.required'         => __('Button text is required'),
            'message.required'          => __('Message is required'),
        ]);

        Setting::where('key', 'cookie_status')->update(['value' => $request->cookie_status]);
        Setting::where('key', 'border')->update(['value' => $request->border]);
        Setting::where('key', 'corners')->update(['value' => $request->corners]);
        Setting::where('key', 'background_color')->update(['value' => $request->background_color]);
        Setting::where('key', 'text_color')->update(['value' => $request->text_color]);
        Setting::where('key', 'border_color')->update(['value' => $request->border_color]);
        Setting::where('key', 'btn_bg_color')->update(['value' => $request->btn_bg_color]);
        Setting::where('key', 'btn_text_color')->update(['value' => $request->btn_text_color]);
        Setting::where('key', 'link_text')->update(['value' => $request->link_text]);
        Setting::where('key', 'btn_text')->update(['value' => $request->btn_text]);
        Setting::where('key', 'message')->update(['value' => $request->message]);
        Setting::where('key', 'link')->update(['value' => $request->link]);

        $this->put_setting_cache();

        $notification = __('Update Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function update_custom_pagination(Request $request)
    {
        checkAdminHasPermissionAndThrowException('setting.update');
        foreach ($request->quantities as $index => $quantity) {
            if ($request->quantities[$index] == '') {
                $notification = [
                    'messege'    => __('Every field are required'),
                    'alert-type' => 'error',
                ];

                return redirect()->back()->with($notification);
            }

            $custom_pagination = CustomPagination::find($request->ids[$index]);
            $custom_pagination->item_qty = $request->quantities[$index];
            $custom_pagination->save();
        }

        // Cache update
        $custom_pagination = CustomPagination::all();
        $pagination = [];
        foreach ($custom_pagination as $item) {
            $pagination[str_replace(' ', '_', strtolower($item->section_name))] = $item->item_qty;
        }
        $pagination = (object) $pagination;
        Cache::put('CustomPagination', $pagination);

        $notification = __('Update Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function update_default_avatar(Request $request)
    {
        checkAdminHasPermissionAndThrowException('setting.update');

        // FT-UPLOAD-3 (GlobalSetting, 2026-05-28) — see update_logo_favicon.
        $request->validate([
            'default_avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
        ]);

        if ($request->file('default_avatar')) {
            $file_name = file_upload($request->default_avatar, 'uploads/custom-images/', $this->cachedSetting?->default_avatar);
            Setting::where('key', 'default_avatar')->update(['value' => $file_name]);
        }

        $this->put_setting_cache();

        $notification = __('Update Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function update_breadcrumb(Request $request)
    {
        checkAdminHasPermissionAndThrowException('setting.update');

        // FT-UPLOAD-3 (GlobalSetting, 2026-05-28) — see update_logo_favicon.
        $request->validate([
            'breadcrumb_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($request->file('breadcrumb_image')) {
            $file_name = file_upload($request->breadcrumb_image, 'uploads/custom-images/', $this->cachedSetting?->breadcrumb_image);
            Setting::where('key', 'breadcrumb_image')->update(['value' => $file_name]);
        }

        $this->put_setting_cache();

        $notification = __('Update Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function update_copyright_text(Request $request)
    {
        checkAdminHasPermissionAndThrowException('setting.update');
        $request->validate([
            'copyright_text' => 'required|string|max:1000',
        ], [
            'copyright_text' => __('Copyright Text is required'),
        ]);
        Setting::where('key', 'copyright_text')->update(['value' => clean($request->copyright_text)]);

        $this->put_setting_cache();

        $notification = __('Update Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function crediential_setting()
    {
        checkAdminHasPermissionAndThrowException('setting.view');

        return view('globalsetting::credientials.index');
    }

    public function update_google_captcha(Request $request)
    {
        checkAdminHasPermissionAndThrowException('setting.update');
        $request->validate([
            'recaptcha_site_key'   => 'required',
            'recaptcha_secret_key' => 'required',
            'recaptcha_status'     => 'required',
        ], [
            'recaptcha_site_key.required'   => __('Site key is required'),
            'recaptcha_secret_key.required' => __('Secret key is required'),
            'recaptcha_status.required'     => __('Status is required'),
        ]);

        Setting::where('key', 'recaptcha_site_key')->update(['value' => $request->recaptcha_site_key]);
        \App\Support\SecretSettings::setValue('recaptcha_secret_key', $request->recaptcha_secret_key);
        Setting::where('key', 'recaptcha_status')->update(['value' => $request->recaptcha_status]);

        $this->put_setting_cache();

        $notification = __('Update Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function update_tawk_chat(Request $request)
    {
        checkAdminHasPermissionAndThrowException('setting.update');
        $request->validate([
            'tawk_status'    => 'required',
            'tawk_chat_link' => 'required',
        ], [
            'tawk_status.required'    => __('Status is required'),
            'tawk_chat_link.required' => __('Chat link is required'),
        ]);

        if (strpos($request->tawk_chat_link, 'embed.tawk.to') !== false) {
            $embedUrl = $request->tawk_chat_link;
        } elseif (strpos($request->tawk_chat_link, 'tawk.to/chat') !== false) {
            $embedUrl = str_replace('tawk.to/chat', 'embed.tawk.to', $request->tawk_chat_link);
        } else {
            $embedUrl = "https://embed.tawk.to/" . $request->tawk_chat_link;
        }

        Setting::where('key', 'tawk_status')->update(['value' => $request->tawk_status]);
        Setting::where('key', 'tawk_chat_link')->update(['value' => $embedUrl]);

        $this->put_setting_cache();

        $notification = __('Update Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function update_google_tagmanager(Request $request)
    {
        checkAdminHasPermissionAndThrowException('setting.update');
        $request->validate([
            'google_tagmanager_status' => 'required',
            'google_tagmanager_id'     => 'required',
        ], [
            'google_tagmanager_status.required' => __('Status is required'),
            'google_tagmanager_id.required'     => __('Tagmanager id is required'),
        ]);

        Setting::where('key', 'google_tagmanager_status')->update(['value' => $request->google_tagmanager_status]);
        Setting::where('key', 'google_tagmanager_id')->update(['value' => $request->google_tagmanager_id]);

        $this->put_setting_cache();

        $notification = __('Update Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }
    public function update_wasabi_cloud(Request $request)
    {
        checkAdminHasPermissionAndThrowException('setting.update');
        $request->validate([
            'wasabi_access_id'  => 'required',
            'wasabi_secret_key' => 'required',
            'wasabi_bucket'     => 'required',
            'wasabi_region'     => 'required',
            'wasabi_status'     => 'required',
        ], [
            'wasabi_access_id.required'  => __('Access ID is required'),
            'wasabi_secret_key.required' => __('Secret key is required'),
            'wasabi_bucket.required'     => __('Bucket name is required'),
            'wasabi_region.required'     => __('Bucket region is required'),
            'wasabi_status.required'     => __('Status is required'),
        ]);
        foreach ($request->except('_token') as $key => $value) {
            \App\Support\SecretSettings::setValue($key, $value);
        }

        $this->put_setting_cache();

        $notification = __('Update Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }
    public function update_aws_cloud(Request $request)
    {
        checkAdminHasPermissionAndThrowException('setting.update');
        $request->validate([
            'aws_access_id'  => 'required',
            'aws_secret_key' => 'required',
            'aws_bucket'     => 'required',
            'aws_region'     => 'required',
            'aws_status'     => 'required',
        ], [
            'aws_access_id.required'  => __('Access ID is required'),
            'aws_secret_key.required' => __('Secret key is required'),
            'aws_bucket.required'     => __('Bucket name is required'),
            'aws_region.required'     => __('Bucket region is required'),
            'aws_status.required'     => __('Status is required'),
        ]);
        foreach ($request->except('_token') as $key => $value) {
            \App\Support\SecretSettings::setValue($key, $value);
        }

        $this->put_setting_cache();

        $notification = __('Update Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function update_google_analytic(Request $request)
    {
        checkAdminHasPermissionAndThrowException('setting.update');
        $request->validate([
            'google_analytic_status' => 'required',
            'google_analytic_id'     => 'required',
        ], [
            'google_analytic_status.required' => __('Status is required'),
            'google_analytic_id.required'     => __('Google analytic id is required'),
        ]);

        Setting::where('key', 'google_analytic_status')->update(['value' => $request->google_analytic_status]);
        Setting::where('key', 'google_analytic_id')->update(['value' => $request->google_analytic_id]);

        $this->put_setting_cache();

        $notification = __('Update Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function update_facebook_pixel(Request $request)
    {
        checkAdminHasPermissionAndThrowException('setting.update');
        $request->validate([
            'pixel_status' => 'required',
            'pixel_app_id' => 'required',
        ], [
            'pixel_status.required' => __('Status is required'),
            'pixel_app_id.required' => __('App id is required'),
        ]);

        Setting::where('key', 'pixel_status')->update(['value' => $request->pixel_status]);
        Setting::where('key', 'pixel_app_id')->update(['value' => $request->pixel_app_id]);

        $this->put_setting_cache();

        $notification = __('Update Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function update_social_login(Request $request)
    {
        checkAdminHasPermissionAndThrowException('setting.update');
        $rules = [
            'google_login_status' => 'required',
            'gmail_client_id'     => 'required',
            'gmail_secret_id'     => 'required',
        ];
        $customMessages = [
            'google_login_status.required' => __('Google is required'),
            'gmail_client_id.required'     => __('Google client is required'),
            'gmail_secret_id.required'     => __('Google secret is required'),
        ];
        $request->validate($rules, $customMessages);

        Setting::where('key', 'google_login_status')->update(['value' => $request->google_login_status]);
        Setting::where('key', 'gmail_client_id')->update(['value' => $request->gmail_client_id]);
        \App\Support\SecretSettings::setValue('gmail_secret_id', $request->gmail_secret_id);
        Setting::where('key', 'gmail_redirect_url')->update(['value' => $request->gmail_redirect_url]);

        $this->put_setting_cache();

        $notification = __('Update Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function update_pusher(Request $request)
    {
        checkAdminHasPermissionAndThrowException('setting.update');
        $request->validate([
            'pusher_status'      => 'required',
            'pusher_app_id'      => 'required',
            'pusher_app_key'     => 'required',
            'pusher_app_secret'  => 'required',
            'pusher_app_cluster' => 'required',
        ], [
            'pusher_status.required'      => __('Status is required'),
            'pusher_app_id.required'      => __('Pusher App id is required'),
            'pusher_app_key.required'     => __('Pusher App Key is required'),
            'pusher_app_secret.required'  => __('Pusher App Secret is required'),
            'pusher_app_cluster.required' => __('Pusher App Cluster is required'),
        ]);

        Setting::where('key', 'pusher_status')->update(['value' => $request->pusher_status]);
        Setting::where('key', 'pusher_app_id')->update(['value' => $request->pusher_app_id]);
        Setting::where('key', 'pusher_app_key')->update(['value' => $request->pusher_app_key]);
        \App\Support\SecretSettings::setValue('pusher_app_secret', $request->pusher_app_secret);
        Setting::where('key', 'pusher_app_cluster')->update(['value' => $request->pusher_app_cluster]);

        $this->put_setting_cache();

        $notification = __('Update Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function seo_setting()
    {
        checkAdminHasPermissionAndThrowException('setting.view');
        $pages = SeoSetting::all();

        return view('globalsetting::seo_setting', compact('pages'));
    }

    public function update_seo_setting(Request $request, $id)
    {
        checkAdminHasPermissionAndThrowException('setting.update');
        $rules = [
            'seo_title'       => 'required',
            'seo_description' => 'required',
        ];
        $customMessages = [
            'seo_title.required'       => __('SEO title is required'),
            'seo_description.required' => __('SEO description is required'),
        ];
        $request->validate($rules, $customMessages);

        $page = SeoSetting::find($id);
        $page->seo_title = $request->seo_title;
        $page->seo_description = $request->seo_description;
        $page->save();

        $notification = __('Update Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function cache_clear()
    {
        checkAdminHasPermissionAndThrowException('setting.update');

        return view('globalsetting::cache_clear');
    }

    public function cache_clear_confirm()
    {
        checkAdminHasPermissionAndThrowException('setting.update');
        Artisan::call('optimize:clear');

        $notification = __('Cache cleared successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function database_clear()
    {
        checkAdminHasPermissionAndThrowException('setting.view');

        return view('globalsetting::database_clear');
    }

    public function database_clear_success(Request $request)
    {
        // Hard lockout in LIVE mode. `migrate:fresh` drops every table —
        // NEVER something a production HTTP endpoint should be able to do.
        // The feature only makes sense for DEMO/TEST installs that need a
        // periodic data reset for site visitors. APP_MODE is the same
        // env-driven flag used by DemoModeMiddleware elsewhere.
        if (strtoupper(config('app.app_mode')) === 'LIVE') {
            \Log::error('database_clear blocked in LIVE mode', ['admin_id' => auth('admin')->id()]);
            abort(403, 'Database reset is not available in LIVE mode.');
        }

        // Permission check before the password compare — gives a clean
        // "you don't have permission" instead of a misleading "wrong password".
        checkAdminHasPermissionAndThrowException('setting.update');

        if (!Hash::check($request->password, auth('admin')->user()->password)) {
            $notification = __('Passwords do not match.');
            $notification = ['messege' => $notification, 'alert-type' => 'error'];
            return redirect()->back()->with($notification);
        }
        // migrate fresh database
        Artisan::call('migrate:fresh');
        // seed database
        Artisan::call('db:seed');
        // optimize clear
        Artisan::call('optimize:clear');

        // delete files
        $this->deleteDirectoryContents(public_path('uploads/custom-images'));
        $this->deleteDirectoryContents(public_path('uploads/forum-images'));
        $this->deleteDirectoryContents(public_path('uploads/store'));

        $notification = __('Database Cleared Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function deleteDirectoryContents($directory)
    {
        // Check if the directory exists
        if (File::exists($directory)) {
            // Delete the directory and its contents
            File::deleteDirectory($directory);
            // Optional: recreate the empty directory if needed
            File::makeDirectory($directory);
            File::put($directory . '/.gitkeep', '');
        }
    }

    public function put_setting_cache()
    {
        $setting_info = Setting::get();

        $setting = [];
        foreach ($setting_info as $setting_item) {
            $setting[$setting_item->key] = $setting_item->value;
        }

        $setting = (object) $setting;

        Cache::put('setting', $setting);
    }

    public function customCode($type)
    {
        checkAdminHasPermissionAndThrowException('setting.view');
        $customCode = CustomCode::first();
        if (!$customCode) {
            $customCode = new CustomCode();
            $customCode->css = "/* write your css code here without the style tag *\\";
            $customCode->javascript = '//write your javascript here without the script tag';
            $customCode->header_javascript = '//write your javascript here without the script tag';
            $customCode->save();
        }
        return view('globalsetting::custom_code_' . $type, compact('customCode'));
    }

    public function customCodeUpdate(Request $request)
    {
        checkAdminHasPermissionAndThrowException('setting.update');
        $validatedData = $request->validate([
            'css'               => 'sometimes',
            'javascript'        => 'sometimes',
            'header_javascript' => 'sometimes',
        ]);

        $customCode = CustomCode::firstOrNew();
        $customCode->fill($validatedData);
        $customCode->save();

        Cache::forget('customCode');

        $notification = __('Updated Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    public function update_maintenance_mode_status()
    {
        checkAdminHasPermissionAndThrowException('setting.update');
        $status = $this->cachedSetting?->maintenance_mode == 1 ? 0 : 1;

        Setting::where('key', 'maintenance_mode')->update(['value' => $status]);

        $this->put_setting_cache();

        return response()->json([
            'success' => true,
            'message' => __('Updated Successfully'),
        ]);
    }

    public function update_maintenance_mode(Request $request)
    {
        checkAdminHasPermissionAndThrowException('setting.update');

        $request->validate([
            'maintenance_title'       => 'required',
            'maintenance_image'       => 'nullable|image|max:2048',
            'maintenance_description' => 'required',
        ], [
            'maintenance_title'       => __('Maintenance Mode Title is required'),
            'maintenance_description' => __('Maintenance Mode Description is required'),
        ]);

        if ($request->hasFile('maintenance_image')) {
            $imagePath = file_upload($request->maintenance_image, 'uploads/custom-images/', $this->cachedSetting?->maintenance_image);
            Setting::where('key', 'maintenance_image')->update(['value' => $imagePath]);
        }

        Setting::where('key', 'maintenance_title')->update(['value' => $request->maintenance_title]);
        Setting::where('key', 'maintenance_description')->update(['value' => $request->maintenance_description]);

        $this->put_setting_cache();

        $notification = __('Update Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }
    public function marketing_setting()
    {
        checkAdminHasPermissionAndThrowException('setting.view');
        return view('globalsetting::marketings.index');
    }
    public function update_google_data_layer(Request $request)
    {
        // FT-IDOR-29 fix (2026-05-28) — was gated on `setting.view`
        // (READ permission) while writing to the marketing_settings
        // table. Same bug class as FT-IDOR-2 (Announcement) and
        // FT-IDOR-17 (InstructorRequest). A read-only sub-admin
        // could overwrite the platform's marketing pixel keys
        // (Google Tag Manager IDs, conversion event toggles).
        // Switch to the write-level slug every other update_* on
        // this controller uses.
        checkAdminHasPermissionAndThrowException('setting.update');

        // FT-VAL-7 — the previous validate(['*' => 'sometimes']) was
        // a no-op. Tighten: every value must be a string capped at
        // 2KB (more than enough for a GTM container ID or a JSON
        // event spec). Length cap is mostly belt-and-suspenders —
        // the marketing_settings table is value:text so it can hold
        // anything, but oversize payloads risk Eloquent / Redis
        // serialisation surprises.
        foreach ($request->except('_token') as $key => $value) {
            if (! is_scalar($value) && ! is_null($value)) {
                continue; // arrays / objects rejected silently
            }
            $stringValue = (string) $value;
            if (strlen($stringValue) > 2048) {
                $stringValue = substr($stringValue, 0, 2048);
            }
            // Only update keys that ALREADY EXIST in the table — the
            // updateOrInsert variant would let an attacker inject
            // arbitrary new key:value pairs (mass-assignment-shaped).
            MarketingSetting::where('key', $key)->update(['value' => $stringValue]);
        }

        Cache::forget('marketing_setting');

        $notification = __('Update Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

}
