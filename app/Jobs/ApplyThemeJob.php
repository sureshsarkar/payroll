<?php

namespace App\Jobs;

use App\Models\Theme;
use App\Models\User;
use App\Services\Theme\ThemeApplicator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Background job: apply a theme to a coach's website.
 *
 * Runs asynchronously after the coach picks a theme in the onboarding
 * wizard. Coach sees a "Your site is being prepared…" UI while the job
 * processes; on completion the coach is redirected to the editor.
 *
 * Idempotency: WithoutOverlapping ensures the same coach+theme pair
 * never applies twice concurrently. Tries = 3 with exponential backoff.
 */
class ApplyThemeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;
    public int $backoff = 10;       // 10s, 20s, 30s

    public function __construct(
        public int $themeId,
        public int $coachId,
        public string $mode = 'replace',
    ) {
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("apply-theme:{$this->coachId}"))->expireAfter(120)];
    }

    public function handle(ThemeApplicator $applicator): void
    {
        $theme = Theme::find($this->themeId);
        $coach = User::find($this->coachId);
        if (! $theme || ! $coach) {
            Log::warning('apply-theme-job: theme or coach missing', [
                'theme_id' => $this->themeId, 'coach_id' => $this->coachId,
            ]);
            return;
        }
        if (! $theme->is_enabled) {
            Log::warning('apply-theme-job: theme disabled', ['theme_id' => $this->themeId]);
            return;
        }

        $result = $applicator->applyToCoach($theme, $coach, $this->mode);

        Log::info('apply-theme-job: success', array_merge(
            ['coach_id' => $coach->id, 'theme_id' => $theme->id],
            $result
        ));
    }
}
