<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        // Enterprise H-A — audit authentication events (login / logout / failed)
        // centrally, with no controller changes. Actor is taken from the event
        // (the guard resolver can't see the user on Logout/Failed).
        Event::listen(Login::class, function (Login $event) {
            $u = $event->user;
            ActivityLogger::log(ActivityLog::LOGIN, 'auth', $u, null, null,
                'Signed in (' . $event->guard . ')',
                [
                    'id'   => $u->id ?? null,
                    'type' => $event->guard === 'admin' ? 'admin' : 'user',
                    'name' => $u->name ?? '',
                    'role' => $event->guard === 'admin' ? 'admin' : ($u->role ?? 'user'),
                ]);
        });

        // Security: alert the user the first time they sign in from a new device.
        Event::listen(Login::class, [\App\Listeners\DetectNewLoginDevice::class, 'handle']);

        Event::listen(Logout::class, function (Logout $event) {
            $u = $event->user;
            if (! $u) {
                return;
            }
            ActivityLogger::log(ActivityLog::LOGOUT, 'auth', $u, null, null,
                'Signed out (' . $event->guard . ')',
                [
                    'id'   => $u->id ?? null,
                    'type' => $event->guard === 'admin' ? 'admin' : 'user',
                    'name' => $u->name ?? '',
                    'role' => $event->guard === 'admin' ? 'admin' : ($u->role ?? 'user'),
                ]);
        });

        Event::listen(Failed::class, function (Failed $event) {
            $email = $event->credentials['email'] ?? 'unknown';
            ActivityLogger::log(ActivityLog::LOGIN_FAILED, 'auth', null, null, null,
                'Failed sign-in for ' . $email . ' (' . $event->guard . ')',
                ['id' => null, 'type' => 'system', 'name' => $email, 'role' => null]);
        });
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
