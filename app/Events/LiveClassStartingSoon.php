<?php

namespace App\Events;

use App\Models\CourseLiveClass;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired by the prenotification:live cron job N minutes before a live class starts.
 * Broadcasts on a per-user private channel so each enrolled student gets the alert.
 *
 * Frontend (Laravel Echo) listens like:
 *   Echo.private('App.Models.User.' + userId)
 *       .listen('LiveClassStartingSoon', (e) => { ... })
 */
class LiveClassStartingSoon implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $userId;
    public int $liveClassId;
    public string $courseTitle;
    public string $startsAt;
    public string $joinUrl;
    public int $minutesUntilStart;

    public function __construct(int $userId, CourseLiveClass $liveClass, string $courseTitle, int $minutesUntilStart)
    {
        $this->userId = $userId;
        $this->liveClassId = $liveClass->id;
        $this->courseTitle = $courseTitle;
        $this->startsAt = (string) $liveClass->start_time;
        $this->joinUrl = (string) ($liveClass->join_url ?? '');
        $this->minutesUntilStart = $minutesUntilStart;
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('App.Models.User.' . $this->userId);
    }

    /**
     * Frontend listens by event name. Must be a string (no namespace).
     */
    public function broadcastAs(): string
    {
        return 'LiveClassStartingSoon';
    }
}
