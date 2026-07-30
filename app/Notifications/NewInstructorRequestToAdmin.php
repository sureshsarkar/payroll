<?php

namespace App\Notifications;

use Modules\InstructorRequest\app\Models\InstructorRequest;

/**
 * Sent to all admins when a user submits a "become instructor" request.
 */
class NewInstructorRequestToAdmin extends InAppNotification
{
    public function __construct(InstructorRequest $req)
    {
        $userName = $req->user?->name ?? 'A user';
        $this->title = $userName . ' applied to become an instructor';
        $this->body = 'Review the application and approve or reject it.';
        $this->url = route('admin.instructor-request.index');
        $this->icon = 'fa-user-plus';
        $this->iconColor = '#3b82f6';
    }
}
