<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\ZoomHealthCheck;
use App\Http\Controllers\Controller;
use App\Models\ZoomCredential;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

/**
 * Admin dashboard for monitoring Zoom OAuth health across all instructors.
 *
 * Backed by the daily zoom:health-check command (see app/Console/Commands).
 * Admins can also force a re-probe from the UI for a single instructor (or
 * all instructors) when investigating a complaint, instead of waiting for
 * the next daily run.
 */
class ZoomHealthController extends Controller
{
    public function index(): View
    {
        checkAdminHasPermissionAndThrowException('setting.view');

        $credentials = ZoomCredential::with(['instructor:id,name,email'])
            ->orderByRaw("FIELD(health_status, 'dead', 'expiring', 'unknown', 'ok')")
            ->orderBy('zoom_token_expires_at', 'asc')
            ->get();

        $tally = [
            'ok'       => $credentials->where('health_status', 'ok')->count(),
            'expiring' => $credentials->where('health_status', 'expiring')->count(),
            'dead'     => $credentials->where('health_status', 'dead')->count(),
            'unknown'  => $credentials->where('health_status', 'unknown')->count(),
            'total'    => $credentials->count(),
        ];

        return view('admin.zoom-health.index', compact('credentials', 'tally'));
    }

    public function probe(?int $instructor_id = null): RedirectResponse
    {
        checkAdminHasPermissionAndThrowException('setting.update');

        $args = ['--quiet-on-ok' => true];
        if ($instructor_id) {
            $args['--instructor'] = $instructor_id;
        }

        try {
            Artisan::call('zoom:health-check', $args);
            $output = trim((string) Artisan::output());
            session()->flash('flash_message', 'Zoom health probe complete. ' . substr($output, -180));
        } catch (\Throwable $e) {
            session()->flash('flash_error', 'Probe failed: ' . $e->getMessage());
        }

        return redirect()->route('admin.zoom-health.index');
    }
}
