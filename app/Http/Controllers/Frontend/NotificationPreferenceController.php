<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Per-user notification preferences page. One toggle per (event × channel).
 *
 * Routes:
 *   GET  /notifications/preferences
 *   POST /notifications/preferences
 */
class NotificationPreferenceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isCoach = $user->role === 'instructor' || !empty($user->coach_id);
        $userRole = $isCoach ? 'instructor' : 'student';

        // Filter the global event list to those relevant to this user's role.
        $events = collect(User::NOTIFICATION_EVENTS)
            ->filter(fn ($cfg) => in_array($userRole, $cfg['roles']))
            ->all();

        $channels = User::NOTIFICATION_CHANNELS;
        $prefs = $user->notification_preferences ?? [];

        return view('frontend.notifications.preferences', compact('events', 'channels', 'prefs'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $isCoach = $user->role === 'instructor' || !empty($user->coach_id);
        $userRole = $isCoach ? 'instructor' : 'student';

        $allowedEvents = collect(User::NOTIFICATION_EVENTS)
            ->filter(fn ($cfg) => in_array($userRole, $cfg['roles']))
            ->keys()
            ->all();

        // Submitted shape: prefs[event][channel] = "1" when checked, missing otherwise.
        $submitted = $request->input('prefs', []);
        $clean = [];

        foreach ($allowedEvents as $event) {
            foreach (User::NOTIFICATION_CHANNELS as $channel) {
                $clean[$event][$channel] = !empty($submitted[$event][$channel]);
            }
        }

        $user->notification_preferences = $clean;
        $user->save();

        return back()->with([
            'messege'    => __('Notification preferences saved.'),
            'alert-type' => 'success',
        ]);
    }
}
