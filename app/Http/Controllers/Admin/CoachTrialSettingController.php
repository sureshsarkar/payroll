<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\ActivityLogger;
use App\Services\CoachTrialService;
use Illuminate\Http\Request;

/**
 * 2026-06-25 — Super-Admin configuration for the coach free-trial system.
 * Read-only knobs persisted to the settings table via CoachTrialService.
 */
class CoachTrialSettingController extends Controller
{
    public function __construct(private CoachTrialService $trial)
    {
    }

    public function edit()
    {
        checkAdminHasPermissionAndThrowException('membership-plan.view');
        $config = $this->trial->config();
        return view('admin.membership.trial-settings', compact('config'));
    }

    public function update(Request $request)
    {
        checkAdminHasPermissionAndThrowException('membership-plan.update');

        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'days'    => ['required', 'integer', 'min:0', 'max:365'],
            'grace'   => ['required', 'integer', 'min:0', 'max:90'],
            'after'   => ['required', 'in:soft,hard,none'],
        ]);

        $before = $this->trial->config();

        $this->trial->save([
            'enabled' => $request->boolean('enabled'),
            'days'    => (int) $data['days'],
            'grace'   => (int) $data['grace'],
            'after'   => $data['after'],
        ]);

        ActivityLogger::log(
            ActivityLog::PLAN_CHANGED, 'coach-trial', null,
            $before, $this->trial->config(), 'Coach trial settings updated'
        );

        return back()->with(['messege' => __('Trial settings saved.'), 'alert-type' => 'success']);
    }
}
