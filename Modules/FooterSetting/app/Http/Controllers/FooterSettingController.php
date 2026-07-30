<?php

namespace Modules\FooterSetting\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\FooterSetting\app\Models\FooterSetting;

class FooterSettingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        checkAdminHasPermissionAndThrowException('footer.management');
        $footerSetting = FooterSetting::first();
        return view('footersetting::index', compact('footerSetting'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        checkAdminHasPermissionAndThrowException('footer.management');

        $validated = $request->validate([
            'footer_text'       => 'nullable|string|max:1000',
            'address'           => 'nullable|string|max:500',
            'phone'             => 'nullable|string|max:50',
            'get_in_touch_text' => 'nullable|string|max:1000',
            'google_play_link'  => 'nullable|url|max:500',
            'apple_store_link'  => 'nullable|url|max:500',
            // FT-UPLOAD-1 fix (2026-05-27) — dropped svg (XSS).
            'logo'              => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $footerSetting = FooterSetting::updateOrCreate(['id' => 1], collect($validated)->except('logo')->toArray());

        if ($request->hasFile('logo')) {
            $fileName = file_upload($request->file('logo'), 'uploads/custom-images/', $footerSetting->logo);
            $footerSetting->update(['logo' => $fileName]);
        }

        return redirect()->back()->with(['messege' => __('Updated successfully'), 'alert-type' => 'success']);
    }

}
