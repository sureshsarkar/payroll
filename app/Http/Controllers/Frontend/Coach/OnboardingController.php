<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use App\Jobs\ApplyThemeJob;
use App\Models\Theme;
use App\Models\ThemeCategory;
use Illuminate\Http\Request;

/**
 * Coach onboarding wizard — runs once after first verified login.
 *
 * Flow:
 *   GET  /onboarding/theme-picker     → gallery of enabled themes
 *   POST /onboarding/apply-theme      → dispatches ApplyThemeJob → progress page
 *   GET  /onboarding/progress         → polls job status; redirects to editor when done
 *
 * Skip logic:
 *   - If coach already has `onboarding_theme_chosen_at`, redirects to editor.
 *   - If coach explicitly clicks "Skip" → marks onboarding done but DOES NOT
 *     apply a theme (coach builds from scratch like today).
 */
class OnboardingController extends Controller
{
    public function themePicker(Request $request)
    {
        $coach = $this->coachOrAbort();

        // Already onboarded → skip to dashboard
        if ($coach->onboarding_theme_chosen_at) {
            return redirect()->route('instructor.web-page.index');
        }

        $categories = ThemeCategory::orderBy('sort_order')->withCount('themes')->get();
        $themes = Theme::enabled()->with('categories')->orderBy('sort_order')->get();

        if ($request->filled('category')) {
            $themes = $themes->filter(fn ($t) => $t->categories->pluck('slug')->contains($request->category));
        }

        return view('frontend.instructor-dashboard.onboarding.theme-picker', [
            'themes'           => $themes,
            'categories'       => $categories,
            'currentCategory'  => $request->query('category', ''),
        ]);
    }

    public function applyTheme(Request $request)
    {
        $coach = $this->coachOrAbort();
        $request->validate(['theme_id' => ['required', 'integer', 'exists:themes,id']]);

        $theme = Theme::enabled()->where('id', $request->theme_id)->first();
        if (! $theme) {
            return back()->with('error', __('That theme is not available right now.'));
        }

        // Dispatch background job
        ApplyThemeJob::dispatch($theme->id, $coach->id);

        return redirect()->route('onboarding.progress', ['theme' => $theme->id]);
    }

    public function progress(Request $request)
    {
        $coach = $this->coachOrAbort();
        $theme = Theme::find($request->query('theme'));

        // Coach is done if they have a recent active application
        $isDone = (bool) \App\Models\ThemeApplication::where('coach_id', $coach->id)
            ->whereNull('superseded_at')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->exists();

        if ($request->wantsJson()) {
            return response()->json(['done' => $isDone]);
        }

        return view('frontend.instructor-dashboard.onboarding.progress', [
            'theme' => $theme,
            'done'  => $isDone,
        ]);
    }

    /** Skip onboarding — coach builds from scratch like the current flow. */
    public function skip(Request $request)
    {
        $coach = $this->coachOrAbort();
        $coach->update(['onboarding_theme_chosen_at' => now()]);
        return redirect()->route('instructor.web-page.index')
            ->with('info', __('You can pick a theme anytime from Site Settings.'));
    }

    private function coachOrAbort()
    {
        $user = userAuth();
        if (! $user || $user->role !== 'instructor') abort(403);
        return $user;
    }
}
