<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

/**
 * Enterprise H-A — read-only admin viewer for the activity/audit log.
 * No create/update/delete: an audit trail is append-only by design.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        checkAdminHasPermissionAndThrowException('activity-log.view');

        $query = ActivityLog::query()->latest('id');

        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('actor_type')) {
            $query->where('actor_type', $request->actor_type);
        }
        if ($request->filled('q')) {
            $q = trim((string) $request->q);
            $query->where(function ($w) use ($q) {
                $w->where('actor_name', 'like', "%{$q}%")
                  ->orWhere('description', 'like', "%{$q}%");
            });
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(30)->withQueryString();

        // Distinct values for the filter dropdowns (cheap; bounded vocab).
        $modules = ActivityLog::query()->select('module')->whereNotNull('module')->distinct()->pluck('module');
        $actions = ActivityLog::query()->select('action')->distinct()->pluck('action');

        return view('admin.activity-logs.index', compact('logs', 'modules', 'actions'));
    }
}
