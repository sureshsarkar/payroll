<?php

namespace Modules\SiteAppearance\app\Http\Controllers;

use App\Enums\ThemeList;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Modules\GlobalSetting\app\Models\Setting;
use Modules\GlobalSetting\app\Http\Controllers\GlobalSettingController;

class SiteAppearanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // FT-IDOR-37 fix — index lacked the `appearance.management`
        // gate that update() already uses. The view renders the
        // appearance-customisation tabs (logo / colours / fonts);
        // sub-admins without the slug shouldn't see the settings
        // they can't change. Same convention as siblings.
        checkAdminHasPermissionAndThrowException('appearance.management');
        return view('siteappearance::index');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        checkAdminHasPermissionAndThrowException('appearance.management');

        $request->validate([
            'theme' => ['required', 'in:' . implode(',', array_column(ThemeList::cases(), 'value'))],
        ], [
            'theme.required' => __('Theme is required'),
        ]);
        Setting::updateOrCreate(
            ['key' => 'site_theme'],
            ['value' => $request->theme]
        );

        Setting::updateOrCreate(
            ['key' => 'show_all_homepage'],
            ['value' => $request->show_all_homepage == 1 ? 1 : 0]
        );
        
        Session::forget('demo_theme');

        $setting_config = new GlobalSettingController();
        $setting_config->put_setting_cache();
        $notification = __('Update Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }
}
