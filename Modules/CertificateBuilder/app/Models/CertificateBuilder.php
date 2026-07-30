<?php

namespace Modules\CertificateBuilder\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\CertificateBuilder\Database\factories\CertificateBuilderFactory;

class CertificateBuilder extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = [];

    /**
     * 2026-06-12 — Per-coach branded certificates. Returns THIS coach's
     * template if they've customised one, else the platform/global default
     * (coach_id NULL), else any legacy single row. So every coach's students
     * get that coach's branding, with a safe fallback.
     */
    public static function forCoach(?int $coachId): ?self
    {
        $global = static::whereNull('coach_id')->first() ?? static::first();

        if (! $coachId) {
            return $global;
        }

        $own = static::where('coach_id', $coachId)->first();
        if (! $own) {
            return $global;
        }

        // 2026-06-12 — COMPOSE: the coach's wording, but fall back to the
        // platform default ARTWORK (background / signature) when the coach
        // hasn't uploaded their own. This stops a coach's certificate from
        // rendering as a plain empty box just because they only edited text.
        // Not persisted — this only affects display/render.
        if ($global) {
            if (empty($own->background)) {
                $own->background = $global->background;
            }
            if (empty($own->signature)) {
                $own->signature = $global->signature;
            }
        }

        return $own;
    }
}
