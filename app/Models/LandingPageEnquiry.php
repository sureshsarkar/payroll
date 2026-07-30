<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LandingPageEnquiry extends Model
{
    use HasFactory;
    use SoftDeletes; // 2026-06-12 Phase 2 — destroy/bulk-delete are recoverable

    /**
     * Enquiry pipeline statuses.
     * Order maps to a sales funnel: new → contacted → qualified → won/lost.
     * The legacy value 'published' (default DB value pre-2026-05-01) is
     * normalized to STATUS_NEW for display via the normalized_status accessor.
     */
    public const STATUS_NEW        = 'new';
    public const STATUS_CONTACTED  = 'contacted';
    public const STATUS_FOLLOWUP   = 'follow_up';   // Phase 8 — added 2026-05-12
    public const STATUS_QUALIFIED  = 'qualified';
    public const STATUS_PROPOSAL   = 'proposal_sent';
    public const STATUS_WON        = 'won';
    public const STATUS_LOST       = 'lost';
    public const STATUS_SPAM       = 'spam';

    /**
     * Status → display label, color, icon. Used by edit-form dropdown and index badges.
     *
     * STATUS_FOLLOWUP (Phase 8) is inserted between Contacted and Qualified
     * because it represents the nurture stage: the coach has reached out,
     * the lead hasn't yet been disqualified, and a follow-up is scheduled.
     * Auto-applied by setFollowUp() when the coach schedules a future
     * reminder.
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_NEW       => ['label' => 'New',           'color' => '#3b82f6', 'icon' => 'fa-circle-plus'],
            self::STATUS_CONTACTED => ['label' => 'Contacted',     'color' => '#8b5cf6', 'icon' => 'fa-phone'],
            self::STATUS_FOLLOWUP  => ['label' => 'Follow-up',     'color' => '#0ea5e9', 'icon' => 'fa-bell'],
            self::STATUS_QUALIFIED => ['label' => 'Qualified',     'color' => '#06b6d4', 'icon' => 'fa-check'],
            self::STATUS_PROPOSAL  => ['label' => 'Proposal Sent', 'color' => '#f59e0b', 'icon' => 'fa-paper-plane'],
            self::STATUS_WON       => ['label' => 'Won',           'color' => '#10b981', 'icon' => 'fa-trophy'],
            self::STATUS_LOST      => ['label' => 'Lost',          'color' => '#ef4444', 'icon' => 'fa-circle-xmark'],
            self::STATUS_SPAM      => ['label' => 'Spam',          'color' => '#6b7280', 'icon' => 'fa-ban'],
        ];
    }

    /**
     * Valid status values for validation rules. Includes 'published' for
     * backward compatibility with existing rows that haven't been re-saved yet.
     */
    public static function validStatuses(): array
    {
        return array_merge(array_keys(self::statusOptions()), ['published']);
    }

    /**
     * Normalize stored status (treats legacy 'published' as 'new').
     */
    public function getNormalizedStatusAttribute(): string
    {
        return $this->status === 'published'
            ? self::STATUS_NEW
            : ($this->status ?? self::STATUS_NEW);
    }

    /**
     * Convenience: status badge metadata for views.
     */
    public function statusBadge(): array
    {
        $opts = self::statusOptions();
        return $opts[$this->normalized_status] ?? $opts[self::STATUS_NEW];
    }

    protected $fillable = [
        'product_id',
        'enquiry_type',
        'coach_id',
        'landing_page_id',
        'page_id',           // CoachPage id (audit 2026-05-25)
        'section_id',        // CoachPageSection id (audit 2026-05-25)
        'template_id',
        'business_category',
        'added_by',
        'assigned_to',
        'first_name',
        'last_name',
        'email',
        'phone',
        'service',
        'source',
        'source_url',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'status',
        'message',
        'custom_fields',     // JSON map of typed lead_form_v1 extras (audit 2026-05-25)
        'follow_up_at',
        'notes_count',
        // 2026-06-12 Phase 2 funnel fields
        'value',
        'lost_reason',
        'won_at',
        'converted_user_id',
        'order_id',
    ];

    protected $casts = [
        'follow_up_at'  => 'datetime',
        'custom_fields' => 'array',
        'value'         => 'decimal:2',
        'won_at'        => 'datetime',
    ];

    /**
     * 2026-06-12 — the student account a WON lead was converted into.
     */
    public function convertedUser()
    {
        return $this->belongsTo(User::class, 'converted_user_id');
    }

    public function productname()
    {
        return $this->belongsTo(Course::class, 'product_id');
    }

    /**
     * Append-only notes timeline. Ordered newest-first so the UI shows
     * the latest interaction on top.
     */
    public function notes()
    {
        return $this->hasMany(LeadNote::class, 'enquiry_id')->orderByDesc('id');
    }

    /**
     * Staff member responsible for following up (for multi-staff coaches).
     * Falls back to coach_id ownership when null.
     */
    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to')->withDefault();
    }

    /**
     * The landing page that captured this enquiry — set at submission time
     * by LandingPageController::submit_landing_page when it matches the
     * subdomain. Null for manual/imported leads.
     */
    public function landingPage()
    {
        return $this->belongsTo(CoachLandingPage::class, 'landing_page_id');
    }

    /**
     * The template the coach was using on that landing page when the lead
     * came in. Powers per-template ROI reporting.
     */
    public function template()
    {
        return $this->belongsTo(
            \Modules\PageTemplateBuilder\app\Models\PageTemplateBuilder::class,
            'template_id'
        );
    }

    /**
     * Human-friendly label for the `source` bucket — used by the CRM detail
     * page so legacy free-text values don't render confusingly next to the
     * structured enum values we write today.
     */
    public function sourceLabel(): string
    {
        $val = (string) ($this->source ?? '');
        return match ($val) {
            'landing_page' => 'Landing Page',
            'service_form' => 'Service Form',
            'manual'       => 'Manual Entry',
            'import'       => 'CSV Import',
            ''             => '—',
            default        => $val,    // legacy free-text URLs survive untouched
        };
    }
}
