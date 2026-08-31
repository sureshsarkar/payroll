<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Language\app\Models\Language;

/**
 * Super-admin entry point.
 *
 * LMS removal phase 2 (2026-08-27) — this class used to be ~485 lines of
 * storefront analytics: order revenue time-series, course counts, pending
 * course approvals, blogs, contact messages and the FinancialReportingService.
 * Every one of those models is gone, so the LMS dashboard went with them.
 *
 * The super-admin's real landing page is the payroll approval dashboard
 * (Modules\Payroll\...\DashboardController::admin, route
 * `admin.payroll.dashboard`), so `admin.dashboard` now just forwards there.
 * The route name is kept because admin auth, the admin layout and a number of
 * redirects still resolve it.
 */
class DashboardController extends Controller
{
    public function dashboard(Request $request): RedirectResponse
    {
        return redirect()->route('admin.payroll.dashboard');
    }

    public function setLanguage()
    {
        Cache::forget('getSocialLinks');

        $lang = Language::whereCode(request('code'))->first();

        if (session()->has('lang')) {
            session()->forget('lang');
            session()->forget('text_direction');
        }
        if ($lang) {
            session()->put('lang', $lang->code);
            session()->put('text_direction', $lang->direction);

            $notification = __('Language Changed Successfully');
            $notification = ['messege' => $notification, 'alert-type' => 'success'];

            return redirect()->back()->with($notification);
        }

        session()->put('lang', config('app.locale'));

        $notification = __('Language Changed Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }
}
