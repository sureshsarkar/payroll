<?php

namespace App\Services\Theme;

use App\Models\User;
use App\Services\BrandResolver;

/**
 * Resolves placeholder tokens like {{brand_name}} / {{coach_name}} /
 * {{primary_color}} inside theme content_json so each coach sees a
 * personalized version of the theme.
 *
 * Used by ThemeApplicator at the moment a theme is cloned into a
 * coach's pages.
 */
class ThemeTokenResolver
{
    /**
     * Walk an array (recursively) and replace any {{token}} strings.
     */
    public function resolve(array $content, User $coach, ?array $extra = null): array
    {
        $tokens = $this->buildTokenMap($coach, $extra ?? []);
        return $this->walk($content, $tokens);
    }

    /**
     * Build the token → value map for this coach.
     */
    protected function buildTokenMap(User $coach, array $extra): array
    {
        // Read raw CoachBrandSetting row (NOT the composed Brand object —
        // composed Brand falls back to platform name, which is wrong for
        // {{brand_name}} when coach hasn't set their own brand_name yet).
        $row = null;
        try { $row = \App\Models\CoachBrandSetting::where('coach_id', $coach->id)->first(); }
        catch (\Throwable $e) {}

        // Use the composed Brand only for COLOURS / SUPPORT EMAIL fallback chain
        $brand = null;
        try { $brand = app(BrandResolver::class)->forCoach($coach->id); }
        catch (\Throwable $e) {}

        $tokens = [
            // {{brand_name}}: explicit coach brand_name → coach's own name.
            // (never platform default — coach should see THEIR identity in placeholders.)
            'brand_name'    => ($row?->brand_name ?: $coach->name) ?? '',
            'coach_name'    => $coach->name  ?? '',
            'coach_email'   => $coach->email ?? '',
            'coach_phone'   => $coach->phone ?? '',
            'coach_role'    => 'Certified Coach',
            'primary_color' => $brand?->primaryColor ?? '#6366F1',
            'accent_color'  => $brand?->accentColor  ?? '#8B5CF6',
            'logo_url'      => $row?->logo_path ? asset($row->logo_path) : '',
            'support_email' => $brand?->supportEmail ?? $coach->email ?? '',
        ];
        return array_merge($tokens, $extra);
    }

    /**
     * Recursive walk: string → token-replace; array → recurse; others passthrough.
     */
    protected function walk($value, array $tokens)
    {
        if (is_string($value)) {
            return preg_replace_callback('/\{\{(\w+)\}\}/', function ($m) use ($tokens) {
                return $tokens[$m[1]] ?? $m[0];
            }, $value);
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->walk($v, $tokens);
            }
            return $out;
        }
        return $value;
    }
}
