<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-(coach, theme) presentation snapshot (Enterprise theme-change, 2026-06-16).
 * Lets a coach switch back to a previously-used theme and get their styling back,
 * while their content (pages/sections/SEO/...) never changes. Tenant-isolated by
 * coach_id.
 */
class CoachThemeSetting extends Model
{
    protected $fillable = ['coach_id', 'theme_id', 'settings_json'];

    protected $casts = ['settings_json' => 'array'];
}
