<?php

namespace App\Exceptions;

use App\Models\CoachLandingPage;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
        // Audit add: don't flash these back to validation forms either —
        // a re-rendered form after a server-side validation failure would
        // otherwise echo them into the HTML response.
        'two_factor_secret',
        'two_factor_recovery_codes',
        'recovery_code',
        'code',                      // 6-digit TOTP
        'forget_password_token',
        'bearer_token',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'UnAuthenticated'], 401);
        }
        $guard = Arr::get($exception->guards(), '0');
        switch ($guard) {
            case 'admin':
                $response = Redirect()->guest('/admin/login');
                break;
            case 'sanctum':
                $response = response()->json(['status' => 'error', 'message' => 'UnAuthenticated'], 401);
                break;
            default:
                $response = Redirect()->guest('/login');
        }
        return $response;
    }

    public function render($request, Throwable $exception)
    {
        if ($exception instanceof AccessPermissionDeniedException || $exception instanceof DemoModeEnabledException) {
            return $exception->render($request);
        }

        // 2026-06-11 — Logout must never dead-end on "419 Page Expired".
        // Logout is idempotent and safe, so an expired/missing CSRF token
        // (idle tab, back-button to a stale page) should still log the user
        // out cleanly instead of showing the framework's 419 page. We handle
        // it HERE — before parent::render() maps TokenMismatchException →
        // HttpException(419) — so the intercept actually catches it. CSRF
        // protection is UNCHANGED for every other route.
        if ($exception instanceof TokenMismatchException && $this->isLogoutRequest($request)) {
            return $this->logoutGracefully($request);
        }

        return parent::render($request, $exception);
    }

    /** Is this request hitting the global or coach logout route? */
    private function isLogoutRequest($request): bool
    {
        if ($request->is('logout') || $request->is('*/logout')) {
            return true;
        }
        $name = optional($request->route())->getName();
        return $name !== null && in_array($name, ['logout', 'coach.logout'], true);
    }

    /**
     * Log the user out cleanly and redirect coach-aware (white-label): coach
     * domain → that coach's home, platform → /login. Mirrors
     * AuthenticatedSessionController::destroy() for the expired-CSRF path.
     */
    private function logoutGracefully($request)
    {
        $coachId = (int) ($request->attributes->get('resolved_coach_id') ?? 0);

        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        $notification = ['messege' => __('Logged out successfully.'), 'alert-type' => 'success'];

        if ($coachId > 0) {
            $slug = CoachLandingPage::where('added_by', $coachId)->value('slug');
            if ($slug) {
                return redirect()->route('coach.site.path', ['site_slug' => $slug])->with($notification);
            }
            return redirect()->to('/')->with($notification);
        }
        return redirect()->route('login')->with($notification);
    }
}
