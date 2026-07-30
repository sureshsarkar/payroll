<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A verifiable certificate credential — one row per (student, course). Its uid
 * is printed on the certificate (ID + QR) and resolved by the public verify page.
 */
class CertificateCredential extends Model
{
    protected $guarded = [];

    protected $casts = [
        'issued_on' => 'date',
    ];

    /**
     * Get (or mint) the credential for a student+course. The uid is stable once
     * issued, so re-downloading the certificate keeps the same verifiable code.
     */
    public static function issueFor(int $userId, int $courseId, array $attrs = []): self
    {
        $row = static::firstOrNew(['user_id' => $userId, 'course_id' => $courseId]);
        if (! $row->exists) {
            $row->uid = static::freshUid();
            $row->issued_on = now()->toDateString();
        }
        // Keep denormalised display fields current (name/title can change).
        $row->fill(array_merge($attrs, ['user_id' => $userId, 'course_id' => $courseId]));
        if (empty($row->uid)) {
            $row->uid = static::freshUid();
        }
        $row->save();
        return $row;
    }

    /** Human-friendly, unambiguous code, e.g. MBS-9F3K7Q2XA4. */
    protected static function freshUid(): string
    {
        do {
            $code = 'MBS-' . strtoupper(Str::random(10));
            // avoid look-alikes
            $code = strtr($code, ['0' => 'A', 'O' => 'B', '1' => 'C', 'I' => 'D', 'L' => 'E']);
        } while (static::where('uid', $code)->exists());
        return $code;
    }
}
