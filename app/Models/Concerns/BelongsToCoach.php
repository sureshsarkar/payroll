<?php

namespace App\Models\Concerns;

use App\Models\Scopes\CoachScope;
use App\Models\User;
use App\Support\TenantAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BelongsToCoach — the reusable tenant-ownership building block (audit Phase 4).
 *
 * A model that owns its rows by a coach simply does `use BelongsToCoach;` and
 * gets the coach() relationship + explicit, intention-revealing scopes, so new
 * tenant tables inherit correct isolation by default instead of re-deriving an
 * ad-hoc `where` each time.
 *
 * The owner column defaults to `coach_id`; override it per model:
 *     protected $coachOwnerColumn = 'instructor_id';   // e.g. courses
 *
 * IMPORTANT — two DIFFERENT tenant contexts, pick the right scope:
 *   • forAuthCoach()       → the COACH PANEL. Scopes to the logged-in coach
 *                            (TenantAccess::authCoachId). Use in /instructor/*.
 *   • forResolvedTenant()  → the PUBLIC WHITE-LABEL SURFACE. Scopes to the
 *                            request's resolved_coach_id (the coach domain).
 *                            No-op on the platform host. Use on coach sites.
 *   • forCoach($id)        → explicit, when you already hold the coach id.
 *
 * Do NOT slap a blanket global scope on a model that is used by BOTH the panel
 * and the public surface — it would be keyed on only one context and silently
 * hide the other's rows. For surface-ONLY models you may opt in to the global
 * scope (CoachScope) in booted(); see that class.
 *
 * bootBelongsToCoach() also stamps the owner column on create from the
 * authenticated coach when it's left empty — so a coach creating a row can't
 * forget to set the owner. It never overwrites an explicit value and is a no-op
 * when there's no auth coach (platform/admin/seeder).
 */
trait BelongsToCoach
{
    public static function bootBelongsToCoach(): void
    {
        static::creating(function ($model) {
            $col = $model->coachColumn();
            if (empty($model->{$col})) {
                $coachId = TenantAccess::authCoachId();
                if ($coachId > 0) {
                    $model->{$col} = $coachId;
                }
            }
        });
    }

    /** The owner column. Override with `protected $coachOwnerColumn = '...'`. */
    public function coachColumn(): string
    {
        return property_exists($this, 'coachOwnerColumn') && is_string($this->coachOwnerColumn)
            ? $this->coachOwnerColumn
            : 'coach_id';
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, $this->coachColumn());
    }

    /** Explicit: rows owned by a specific coach. */
    public function scopeForCoach(Builder $query, int $coachId): Builder
    {
        return $query->where($this->qualifyColumn($this->coachColumn()), $coachId);
    }

    /** Coach PANEL: rows owned by the authenticated coach (0 ⇒ unfiltered). */
    public function scopeForAuthCoach(Builder $query): Builder
    {
        $coachId = TenantAccess::authCoachId();

        return $query->when($coachId > 0, fn (Builder $q) => $q->where($this->qualifyColumn($this->coachColumn()), $coachId));
    }

    /** Public WHITE-LABEL surface: rows for the request's resolved coach domain
     *  (no-op on the platform host, so the platform is unchanged). */
    public function scopeForResolvedTenant(Builder $query): Builder
    {
        $coachId = (int) (request()->attributes->get('resolved_coach_id') ?? 0);

        return $query->when($coachId > 0, fn (Builder $q) => $q->where($this->qualifyColumn($this->coachColumn()), $coachId));
    }

    /** Opt-in helper to register the surface-only global scope from booted(). */
    protected static function addCoachGlobalScope(): void
    {
        static::addGlobalScope(new CoachScope);
    }
}
