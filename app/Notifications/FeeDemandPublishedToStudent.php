<?php

namespace App\Notifications;

use App\Models\FeeDemand;

/**
 * Sent to enrolled students when a coach publishes a new fee demand
 * against their batch. Phase 4C.
 */
class FeeDemandPublishedToStudent extends InAppNotification
{
    protected string $event = 'fee_demand_published';
    protected string $emailTemplate = 'notif_fee_demand_published';
    protected string $emailCtaLabel = 'View my fees';

    private FeeDemand $demand;
    private string $batchTitle;

    public function __construct(FeeDemand $demand, string $batchTitle)
    {
        $this->demand = $demand;
        $this->coachId = $demand->coach_id ? (int) $demand->coach_id : null;
        $this->batchTitle = $batchTitle;

        $this->title = 'New fee due';
        $this->body = '"' . \Str::limit($demand->title, 50) . '"'
            . ' — ' . formatMoney($demand->amount)
            . ($demand->due_date ? ' due by ' . $demand->due_date->format('M j, Y') : '');
        $this->icon = 'fa-coins';
        $this->iconColor = '#f59e0b';
        $this->url = route('student.fees.index');
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'user_name'   => $notifiable->name ?? '',
            'fee_title'   => $this->demand->title,
            'amount'      => formatMoney($this->demand->amount),
            'batch_title' => $this->batchTitle,
            'due_date'    => $this->demand->due_date
                ? $this->demand->due_date->format('M j, Y') : 'No specific due date',
            'pay_url'     => $this->url,
        ];
    }
}
