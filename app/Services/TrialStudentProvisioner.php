<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\CoachStudentLink;
use App\Models\CoachTrialEnquiry;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Provisions a student account from a completed trial-session enquiry
 * (2026-07-03). Mirrors the Coach-Panel "Add Student" logic exactly:
 *   - reuses an existing student (globally-unique email) and just links them to
 *     this coach — no new password, no credentials email;
 *   - creates a fresh student (role=student, active, email-verified, added_by
 *     coach) with a secure random password, linked to the coach's roster.
 *
 * Concurrency-safe (row lock + unique-email arbiter) so two payment callbacks
 * can never create two accounts. The plain password is returned ONCE to the
 * caller for the welcome email and is never stored or logged.
 */
class TrialStudentProvisioner
{
    /**
     * @return array{created:bool, user:?User, plain:?string, reason:?string}
     *   created  — true only when a brand-new account was made
     *   user     — the linked/created student (null if it couldn't be provisioned)
     *   plain    — the one-time plaintext password (only when created)
     *   reason   — 'email_non_student' | 'invalid' | 'already_linked' | null
     */
    public function provision(CoachTrialEnquiry $enquiry): array
    {
        $coachId = (int) $enquiry->coach_id;
        $email   = trim((string) $enquiry->email);

        if ($coachId <= 0 || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['created' => false, 'user' => null, 'plain' => null, 'reason' => 'invalid'];
        }

        // Already provisioned for this enquiry → ensure the link, no-op otherwise.
        if ($enquiry->student_id) {
            $u = User::find($enquiry->student_id);
            if ($u) {
                CoachStudentLink::link($coachId, (int) $u->id, 'purchase');
                return ['created' => false, 'user' => $u, 'plain' => null, 'reason' => 'already_linked'];
            }
        }

        $plain = null; $created = false; $user = null; $reason = null;

        DB::transaction(function () use (&$plain, &$created, &$user, &$reason, $enquiry, $coachId, $email) {
            // Lock the email row (if any) so concurrent callbacks serialise.
            $existing = User::where('email', $email)->lockForUpdate()->first();

            if ($existing) {
                // The email is globally unique. A non-student owner (coach/admin)
                // must never be converted — skip provisioning safely.
                if ($existing->role !== 'student') {
                    $reason = 'email_non_student';
                    return;
                }
                CoachStudentLink::link($coachId, (int) $existing->id, 'purchase');
                $enquiry->forceFill(['student_id' => $existing->id, 'student_was_new' => false])->save();
                $user = $existing;
                return;
            }

            $plain = $this->generatePassword();
            try {
                $user = User::create([
                    'role'              => 'student',
                    'name'              => $enquiry->name ?: 'Student',
                    'email'             => $email,
                    'password'          => Hash::make($plain),
                    'status'            => 'active',
                    'is_banned'         => 'no',
                    'email_verified_at' => now(),
                    'added_by'          => $coachId,
                ]);
            } catch (QueryException $e) {
                // A parallel request won the unique(email) race — reuse + link it.
                $winner = User::where('email', $email)->first();
                if ($winner && $winner->role === 'student') {
                    CoachStudentLink::link($coachId, (int) $winner->id, 'purchase');
                    $enquiry->forceFill(['student_id' => $winner->id, 'student_was_new' => false])->save();
                    $user = $winner; $plain = null;
                    return;
                }
                throw $e;
            }

            CoachStudentLink::link($coachId, (int) $user->id, 'purchase');
            $enquiry->forceFill(['student_id' => $user->id, 'student_was_new' => true])->save();
            $created = true;
        });

        if ($reason === 'email_non_student') {
            Log::warning('trial-student-provision: email belongs to a non-student account', [
                'email' => $email, 'coach_id' => $coachId,
            ]);
            return ['created' => false, 'user' => null, 'plain' => null, 'reason' => $reason];
        }

        if ($user) {
            $this->audit($user, $coachId, $created);
        }

        return ['created' => $created, 'user' => $user, 'plain' => $created ? $plain : null, 'reason' => $reason];
    }

    /** A 10–12 char password with upper, lower, digit and special characters. */
    private function generatePassword(): string
    {
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';   // no I/O (ambiguous)
        $lower = 'abcdefghijkmnpqrstuvwxyz';   // no l
        $digit = '23456789';                   // no 0/1
        $spec  = '!@#$%^&*?';
        $all   = $upper . $lower . $digit . $spec;

        $chars = [
            $upper[random_int(0, strlen($upper) - 1)],
            $lower[random_int(0, strlen($lower) - 1)],
            $digit[random_int(0, strlen($digit) - 1)],
            $spec[random_int(0, strlen($spec) - 1)],
        ];
        $len = random_int(10, 12);
        for ($i = count($chars); $i < $len; $i++) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }
        // Fisher–Yates shuffle so the guaranteed classes aren't always at the front.
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }
        return implode('', $chars);
    }

    private function audit(User $user, int $coachId, bool $created): void
    {
        try {
            ActivityLogger::log(
                $created ? ActivityLog::CREATED : ActivityLog::UPDATED,
                'student',
                $user,
                null,
                ['name' => $user->name, 'email' => $user->email, 'status' => $user->status],
                ($created ? 'Auto-created student from trial-session payment' : 'Linked existing student from trial-session payment')
                    . ' (coach #' . $coachId . ')'
            );
        } catch (\Throwable $e) {
            Log::warning('trial-student-provision audit failed: ' . $e->getMessage());
        }
    }
}
