<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\PageTemplateBuilder\app\Models\PageTemplateBuilder;
use Modules\PageTemplateBuilder\app\Models\PageTemplateCategory;

/**
 * Seed business-vertical landing-page templates — Academy, Institute, School,
 * Gym, Training Center, Coaching, Yoga.
 *
 * Phase 1 of the multi-template business website system (2026-05-12). This is
 * a one-shot setup command that's safe to re-run — every insert is keyed by
 * name so existing rows are left untouched. We also rename the legacy
 * "Learning Management" category to "Coaching" so the catalog aligns with
 * how coaches actually describe their work.
 */
class SeedBusinessTemplates extends Command
{
    protected $signature = 'business-templates:seed {--dry-run : Print actions without writing}';

    protected $description = 'Seed the seven business-vertical landing-page templates and their categories';

    /**
     * Category name → status. Insert-if-missing.
     */
    private array $categories = [
        'Academy', 'Institute', 'School', 'Gym', 'Training Center', 'Coaching', 'Yoga',
    ];

    /**
     * The seven business-vertical templates. Each row references its category by
     * NAME (resolved to id at insert time), and points at a Blade file + SVG
     * thumbnail under public/uploads/page-template-builder/.
     */
    private array $templates = [
        ['name' => 'Academy Pro',          'category' => 'Academy',          'file' => 'uploads/page-template-builder/business-academy.blade.php',          'image' => 'uploads/page-template-builder/thumb-academy.svg'],
        ['name' => 'Institute Pro',        'category' => 'Institute',        'file' => 'uploads/page-template-builder/business-institute.blade.php',        'image' => 'uploads/page-template-builder/thumb-institute.svg'],
        ['name' => 'BrightSchool',         'category' => 'School',           'file' => 'uploads/page-template-builder/business-school.blade.php',           'image' => 'uploads/page-template-builder/thumb-school.svg'],
        ['name' => 'IronForge Gym',        'category' => 'Gym',              'file' => 'uploads/page-template-builder/business-gym.blade.php',              'image' => 'uploads/page-template-builder/thumb-gym.svg'],
        ['name' => 'TrainHub',             'category' => 'Training Center',  'file' => 'uploads/page-template-builder/business-training-center.blade.php',  'image' => 'uploads/page-template-builder/thumb-training-center.svg'],
        ['name' => 'Coach Pro',            'category' => 'Coaching',         'file' => 'uploads/page-template-builder/business-coaching.blade.php',         'image' => 'uploads/page-template-builder/thumb-coaching.svg'],
        ['name' => 'Prana Yoga',           'category' => 'Yoga',             'file' => 'uploads/page-template-builder/business-yoga.blade.php',             'image' => 'uploads/page-template-builder/thumb-yoga.svg'],
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $now = now();

        // Step 1: rename "Learning Management" → "Coaching" if it still exists.
        // The legacy name is too vague and clashes with what we want the catalog
        // to mean. Templates point at the category by id, so the rename is safe.
        $legacy = PageTemplateCategory::where('name', 'Learning Management')->first();
        if ($legacy && !PageTemplateCategory::where('name', 'Coaching')->where('id', '!=', $legacy->id)->exists()) {
            $this->line("Renaming category #{$legacy->id} 'Learning Management' → 'Coaching'");
            if (!$dry) {
                $legacy->update(['name' => 'Coaching']);
            }
        }

        // Step 2: insert any missing business-vertical categories. Idempotent.
        $insertedCategories = [];
        foreach ($this->categories as $name) {
            $exists = PageTemplateCategory::where('name', $name)->first();
            if ($exists) {
                if ($exists->status != 1) {
                    $this->line("Reactivating category '{$name}' (was inactive)");
                    if (!$dry) {
                        $exists->update(['status' => 1]);
                    }
                }
                continue;
            }
            $this->line("Inserting category: {$name}");
            if (!$dry) {
                $row = PageTemplateCategory::create([
                    'name'       => $name,
                    'status'     => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $insertedCategories[] = $row->id;
            }
        }

        // Step 3: resolve category name → id, then insert any missing templates.
        $categoryMap = PageTemplateCategory::pluck('id', 'name')->all();

        foreach ($this->templates as $tpl) {
            $catId = $categoryMap[$tpl['category']] ?? null;
            if (!$catId) {
                $this->error("Skipping '{$tpl['name']}' — category '{$tpl['category']}' not found");
                continue;
            }

            $exists = PageTemplateBuilder::where('template_name', $tpl['name'])->first();
            if ($exists) {
                // Keep existing rows untouched in case a coach has selected one — only
                // patch the file/image/category if any of them drifted.
                $needsPatch = $exists->category != $catId
                    || $exists->file !== $tpl['file']
                    || $exists->image !== $tpl['image'];
                if ($needsPatch) {
                    $this->line("Re-aligning template '{$tpl['name']}' (category/file/image drift)");
                    if (!$dry) {
                        $exists->update([
                            'category' => $catId,
                            'file'     => $tpl['file'],
                            'image'    => $tpl['image'],
                            'status'   => 1,
                        ]);
                    }
                }
                continue;
            }

            $this->line("Inserting template: {$tpl['name']} → {$tpl['category']}");
            if (!$dry) {
                PageTemplateBuilder::create([
                    'template_name' => $tpl['name'],
                    'category'      => $catId,
                    'file'          => $tpl['file'],
                    'image'         => $tpl['image'],
                    'status'        => 1,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }
        }

        if ($dry) {
            $this->warn('Dry-run complete — no rows written.');
            return self::SUCCESS;
        }

        $this->info('Business templates seeded.');
        $this->table(['Category', 'Templates'], $this->summary());
        return self::SUCCESS;
    }

    private function summary(): array
    {
        $rows = [];
        foreach (PageTemplateCategory::orderBy('id')->get() as $cat) {
            $names = PageTemplateBuilder::where('category', $cat->id)->where('status', 1)->pluck('template_name')->all();
            $rows[] = [$cat->name . ($cat->status ? '' : ' (off)'), $names ? implode(', ', $names) : '—'];
        }
        return $rows;
    }
}
