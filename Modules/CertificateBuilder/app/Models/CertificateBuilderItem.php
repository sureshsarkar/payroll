<?php

namespace Modules\CertificateBuilder\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\CertificateBuilder\Database\factories\CertificateBuilderItemFactory;

class CertificateBuilderItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = [];

    /**
     * 2026-06-12 — element positions for THIS coach's certificate, else the
     * global default (coach_id NULL), else legacy all. Mirrors
     * CertificateBuilder::forCoach() so content + layout stay consistent.
     *
     * 2026-07-09 — merge fix: a coach who has dragged SOME elements but not
     * others used to get only their own rows, so any un-positioned element
     * (e.g. the description) rendered at top:0 and overlapped the header — in
     * both the live preview AND the exported classic PDF. We now backfill any
     * missing element with the global default so every element is positioned.
     */
    public static function forCoach(?int $coachId)
    {
        $global = static::whereNull('coach_id')->get();

        if ($coachId && static::where('coach_id', $coachId)->exists()) {
            $own = static::where('coach_id', $coachId)->get();
            $ownIds = $own->pluck('element_id')->all();
            // Backfill only the elements the coach hasn't positioned yet.
            $fill = $global->whereNotIn('element_id', $ownIds);

            return $own->concat($fill)->values();
        }

        return $global->isNotEmpty() ? $global : static::all();
    }
}
