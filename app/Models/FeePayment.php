<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FeePayment — Phase 4B foundation.
 *
 * One student's response to one FeeDemand. Captures both gateway
 * (Razorpay/Stripe) and offline (cash/cheque/manual) payments under
 * a single normalised shape.
 */
class FeePayment extends Model
{
    protected $fillable = [
        'fee_demand_id', 'student_id', 'receipt_no', 'amount',
        'gateway', 'gateway_txn_id', 'reference_no', 'status', 'paid_at',
        'refunded_at', 'note', 'recorded_by',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'paid_at'     => 'datetime',
        'refunded_at' => 'datetime',
    ];

    /* ─────────────────── relations ─────────────────── */

    public function demand(): BelongsTo
    {
        return $this->belongsTo(FeeDemand::class, 'fee_demand_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /* ─────────────────── helpers ─────────────────── */

    /**
     * Generate a fresh, unique receipt number in the format
     *   RCP-YYYYMM-XXXXXXXX
     * where XXXXXXXX is 8 base32 chars. Collisions retry once.
     */
    public static function generateReceiptNo(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $rand = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $candidate = sprintf('RCP-%s-%s', now()->format('Ym'), $rand);
            if (!self::where('receipt_no', $candidate)->exists()) {
                return $candidate;
            }
        }
        // Hit the rare 5-collision case → append microseconds
        return sprintf('RCP-%s-%s%s',
            now()->format('Ym'),
            strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)),
            substr((string) microtime(true), -4),
        );
    }

    /* ─────────────────── scopes ─────────────────── */

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeRefunded($query)
    {
        return $query->where('status', 'refunded');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
