<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\GlobalSetting\app\Models\Setting;

/**
 * Audit 2026-05-18 phase 5 — admin UI for attendance verification settings.
 *
 * Currently exposes the single global `attendance_min_percent` value
 * (seeded by migration 200000). Future per-batch overrides can be added
 * here without changing the storage shape.
 *
 * Permission gate: reuses `dashboard.view` so all admins (incl. the
 * "Announcement Manager" delegated role) can read; only those with
 * `setting.update` can write. If the latter permission does not exist
 * in this deployment, the gate falls back to a hard Super-Admin check.
 */
class AttendanceSettingsController extends Controller
{
    public function show()
    {
        checkAdminHasPermissionAndThrowException('dashboard.view');

        $current = (int) (Setting::where('key', 'attendance_min_percent')->value('value') ?? 50);

        return view('admin.attendance-settings.show', [
            'current' => max(1, min(100, $current)),
        ]);
    }

    public function update(Request $request)
    {
        // FT-IDOR-39 fix (2026-05-28) — the pre-fix fallback chain
        // was:
        //   try setting.update OR settings.view OR fall back to
        //   dashboard.view
        //
        // Two problems:
        //  (a) `settings.view` (plural) is a typo of the registered
        //      `setting.view` slug — neither exists in PermissionsTrait
        //      so the OR-branch never matches.
        //  (b) The terminal fallback to `dashboard.view` means EVERY
        //      sub-admin role (which all have dashboard.view by
        //      design) can update this platform-wide setting. Same
        //      shape as FT-IDOR-38: write surface gated on a read
        //      permission via a permissive fallback.
        //
        // The setting (attendance_min_percent) is a global threshold
        // that affects every coach's attendance reports. A sub-admin
        // setting it to 100 would mark every student "not enough
        // attendance" on every dashboard, breaking certificate
        // issuance flows that consume the threshold.
        //
        // Replace with the canonical FT-IDOR-28 pattern: require
        // setting.update outright.
        checkAdminHasPermissionAndThrowException('setting.update');

        $request->validate([
            'attendance_min_percent' => 'required|integer|min:1|max:100',
        ]);

        Setting::updateOrCreate(
            ['key' => 'attendance_min_percent'],
            ['value' => (string) $request->attendance_min_percent]
        );

        // Setting model observer (phase 5) auto-clears the 'setting' cache.

        return back()->with([
            'messege'    => __('Attendance threshold updated to :pct%.', ['pct' => $request->attendance_min_percent]),
            'alert-type' => 'success',
        ]);
    }
}
