<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Order\app\Models\OrderItem;

/**
 * Backfill a coach's in-app notification inbox from their EXISTING paid sales.
 *
 * The coach notification API (GET /api/instructor/notifications) reads
 * $user->notifications() — it's correct, but a coach whose sales predate the
 * notification code has an empty inbox. This one-off command creates database
 * notification rows (same {title,body,url,icon,iconColor} shape the app reads)
 * from historical paid order items, so the inbox immediately shows real data
 * and the read pipeline is proven end-to-end. Idempotent per run via a marker.
 *
 * Usage:  php artisan coach:backfill-notifications 1079 --limit=50
 */
class BackfillCoachNotifications extends Command
{
    protected $signature = 'coach:backfill-notifications {coach_id} {--limit=50}';
    protected $description = 'Create inbox notifications for a coach from their existing paid sales';

    public function handle(): int
    {
        $coachId = (int) $this->argument('coach_id');
        $coach   = User::find($coachId);
        if (! $coach) {
            $this->error("Coach {$coachId} not found.");
            return 1;
        }

        $courseIds = Course::where('instructor_id', $coachId)->pluck('id');
        if ($courseIds->isEmpty()) {
            $this->warn("Coach {$coachId} owns no courses — nothing to backfill.");
            return 0;
        }

        $items = OrderItem::with(['order.user', 'course:id,title'])
            ->whereIn('course_id', $courseIds)
            ->whereHas('order', fn ($q) => $q->where('payment_status', 'paid'))
            ->orderByDesc('id')
            ->limit((int) $this->option('limit'))
            ->get();

        $created = 0;
        foreach ($items as $it) {
            $marker = 'sale:' . $it->id;
            // Skip if we already backfilled this sale (idempotent).
            $exists = DB::table('notifications')
                ->where('notifiable_type', get_class($coach))
                ->where('notifiable_id', $coach->id)
                ->where('data', 'like', '%"marker":"' . $marker . '"%')
                ->exists();
            if ($exists) continue;

            DB::table('notifications')->insert([
                'id'              => (string) Str::uuid(),
                'type'            => 'App\\Notifications\\CourseSaleToCoach',
                'notifiable_type' => get_class($coach),
                'notifiable_id'   => $coach->id,
                'data'            => json_encode([
                    'title'     => 'Sale: ' . Str::limit((string) ($it->course?->title ?? 'course'), 50),
                    'body'      => (($it->order?->user?->name ?? 'A student'))
                        . ' purchased "' . Str::limit((string) ($it->course?->title ?? ''), 50) . '".',
                    'url'       => null,
                    'icon'      => 'fa-cart-shopping',
                    'iconColor' => '#16a34a',
                    'marker'    => $marker,
                ]),
                'read_at'    => null,
                'created_at' => $it->created_at ?? now(),
                'updated_at' => now(),
            ]);
            $created++;
        }

        $this->info("Backfilled {$created} sale notification(s) for coach {$coachId}.");
        return 0;
    }
}
