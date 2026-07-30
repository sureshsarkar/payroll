<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * CoachScope — OPT-IN global scope for models served ONLY on the public
 * white-label surface (audit Phase 4).
 *
 * Auto-filters every query to the request's resolved coach domain
 * (resolved_coach_id). On the platform host (id 0) it is a no-op, so the
 * platform is unchanged.
 *
 * ⚠️ Use ONLY on models that are NOT also queried by the coach panel. The panel
 * runs on the platform host (resolved_coach_id = 0) authenticated as the coach,
 * so this scope would be a no-op there and expose every coach's rows. Panel
 * models must scope with BelongsToCoach::forAuthCoach() instead.
 *
 * Opt in from a model that uses BelongsToCoach:
 *     protected static function booted(): void { static::addCoachGlobalScope(); }
 *
 * Bypass when needed:  Model::withoutGlobalScope(CoachScope::class)->...
 */
class CoachScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $coachId = (int) (request()->attributes->get('resolved_coach_id') ?? 0);
        if ($coachId <= 0) {
            return; // platform host — no tenant filter
        }

        $column = method_exists($model, 'coachColumn') ? $model->coachColumn() : 'coach_id';
        $builder->where($model->qualifyColumn($column), $coachId);
    }
}
