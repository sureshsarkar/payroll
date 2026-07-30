<?php

namespace Tests\Feature\Audit;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\PaymentWithdraw\app\Models\WithdrawRequest;
use Tests\TestCase;

/**
 * Verifies the audit's wallet-decrement guarantees:
 *
 * 1. WithdrawRequest::account_info has Eloquent encrypted cast.
 * 2. account_info written to the model is encrypted at rest in the DB.
 * 3. account_info read back from the model is decrypted (transparent to consumers).
 *
 * NOTE: the atomic-decrement / lockForUpdate guarantee in
 * `WithdrawMethodController::update_withdraw` is exercised via integration
 * tests (real concurrent admin approvals), not unit tests — that's a
 * production-only verification.
 */
class WalletTest extends TestCase
{
    use DatabaseTransactions;

    public function test_withdraw_request_model_has_encrypted_cast_on_account_info(): void
    {
        $model = new WithdrawRequest();
        $casts = $model->getCasts();
        $this->assertArrayHasKey('account_info', $casts, 'WithdrawRequest must cast account_info');
        $this->assertSame('encrypted', $casts['account_info'], 'account_info cast must be `encrypted` (audit hardening)');
    }

    public function test_account_info_round_trips_through_encryption(): void
    {
        // Audit 2026-05-19 — factory fixture so the test runs in any env.
        $user = User::first() ?? User::factory()->create(['role' => 'student']);

        $sensitive = 'PhonePe: 9876543210 / IFSC: PYTM0123456';

        $row = WithdrawRequest::create([
            'user_id' => $user->id,
            'withdraw_amount' => 1.00,
            'method' => 'PhonePe',
            'account_info' => $sensitive,
            'status' => 'pending',
            'current_amount' => 1.00,
        ]);

        // Read back via Eloquent — should be decrypted plaintext
        $back = WithdrawRequest::find($row->id);
        $this->assertSame($sensitive, $back->account_info, 'account_info must round-trip through encryption');

        // But the raw DB value should be ciphertext, not the plaintext
        $rawValue = DB::table('withdraw_requests')->where('id', $row->id)->value('account_info');
        $this->assertNotSame($sensitive, $rawValue, 'raw DB value must NOT be plaintext (encryption requirement)');
        $this->assertGreaterThan(
            strlen($sensitive),
            strlen((string) $rawValue),
            'Encrypted ciphertext should be substantially longer than the plaintext'
        );
    }
}
