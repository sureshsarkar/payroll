<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Debug helper for admin:smoke-tier-a — dispatches one URL and reports
 * the underlying exception class + message, bypassing the rendered
 * exception page.
 *
 * Used to triage 500-class failures by their root cause rather than by
 * status code alone.
 */
class AdminSmokeDebug extends Command
{
    protected $signature = 'admin:smoke-debug {url}';
    protected $description = 'Hit one admin URL as Super Admin and report the underlying exception class + message.';

    public function handle(): int
    {
        $admin = Admin::find(1);
        Auth::guard('admin')->loginUsingId($admin->id);

        $url = $this->argument('url');
        if (!str_starts_with($url, '/')) $url = '/' . $url;

        $request = Request::create($url, 'GET');
        $request->setLaravelSession(app('session.store'));

        try {
            // Don't go through the kernel's exception renderer — dispatch directly
            $router = app('router');
            $route = $router->getRoutes()->match($request);
            app()->instance('request', $request);
            $response = $route->run();
            $this->info("OK: $url");
            return 0;
        } catch (\Throwable $e) {
            $this->error(sprintf(
                "%s\n  %s\n  at %s:%d",
                get_class($e),
                $e->getMessage(),
                str_replace('C:\\xampp\\htdocs\\MBSGuru1\\', '', $e->getFile()),
                $e->getLine(),
            ));
            return 1;
        }
    }
}
