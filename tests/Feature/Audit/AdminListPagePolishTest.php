<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * Regression tripwire for the M-batch admin-list polish fixes
 * (2026-05-12). Each list page's icon-only action buttons (edit / delete /
 * eye) used to render as `<a class="btn"><i class="fa fa-edit"></i></a>`
 * with no accessible label. Screen-reader users hit "button button button"
 * with no idea which row each action targets.
 *
 * This test verifies the three highest-volume admin list pages
 * (admin-list, roles, membership-plans) now carry either `aria-label`
 * or `title` (which screen readers also announce) on every icon-only
 * action button. Adding a new list page should mirror the pattern.
 */
class AdminListPagePolishTest extends TestCase
{
    /**
     * Each path → at least this many `aria-label=` AND `title=` attribute
     * occurrences should appear (one per icon-only action button).
     */
    private const REQUIRED_LABEL_COUNTS = [
        'views/admin/admin-list/admin.blade.php'         => 2,  // edit + delete
        'views/admin/roles/index.blade.php'              => 2,
        'views/admin/membership/plans/index.blade.php'   => 2,
    ];

    public function test_icon_only_action_buttons_carry_aria_label(): void
    {
        foreach (self::REQUIRED_LABEL_COUNTS as $relPath => $minCount) {
            $src = (string) file_get_contents(resource_path($relPath));
            $count = substr_count($src, 'aria-label=');
            $this->assertGreaterThanOrEqual(
                $minCount, $count,
                "{$relPath} has only {$count} aria-label attributes; expected ≥ {$minCount} (one per icon-only action button). Screen-reader users can't tell edit from delete."
            );
        }
    }

    public function test_icon_only_action_buttons_carry_title_tooltip(): void
    {
        foreach (self::REQUIRED_LABEL_COUNTS as $relPath => $minCount) {
            $src = (string) file_get_contents(resource_path($relPath));
            $count = substr_count($src, 'title=');
            $this->assertGreaterThanOrEqual(
                $minCount, $count,
                "{$relPath} has only {$count} title attributes; expected ≥ {$minCount} on action buttons. Sighted users get a tooltip on hover."
            );
        }
    }

    public function test_master_layout_avatar_alt_text_is_meaningful(): void
    {
        // `alt="image"` is essentially the same as no alt at all — screen
        // readers announce it but it carries no information. Avatar in the
        // navbar should be `{name} avatar` or similar.
        $src = (string) file_get_contents(
            resource_path('views/admin/master_layout.blade.php')
        );

        $this->assertStringNotContainsString(
            'alt="image"', $src,
            'master_layout still uses the generic alt="image" — screen readers gain nothing from that. Use the admin name in the alt text.'
        );
        $this->assertMatchesRegularExpression(
            '/alt="\{\{\s*\$header_admin->name[^"]+avatar/u',
            $src,
            'navbar avatar alt text must include the admin name + "avatar" so screen readers announce *whose* avatar this is'
        );
    }
}
