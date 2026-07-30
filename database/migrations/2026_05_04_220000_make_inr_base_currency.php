<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Switches INR from "display currency converted from USD base" to actual base
 * currency. Concretely:
 *   - INR rate becomes 1.00 (it IS the base now, no multiplier)
 *   - USD/NGN/CAD/PHP/BDT rates are recomputed as conversions FROM INR
 *   - The cached settings row for currency_code is set to INR
 *
 * Existing numeric prices (courses, plans, etc.) are reinterpreted as INR.
 * E.g. a course with price=999 was previously showing as $999 * 91.88 = ₹91,788.
 * Now it shows as ₹999 — that's the intent.
 *
 * Default plan prices for membership are also rewritten to round INR amounts
 * (Coach Monthly: ₹999, Yearly: ₹9,999, Lifetime: ₹29,999).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Snapshot the old INR rate so we can convert other currencies relative
        // to the new INR=1 base. Old rate represents "1 USD = X INR" if USD was
        // previously rate=1.
        $oldInrRate = (float) DB::table('multi_currencies')
            ->where('currency_code', 'INR')->value('currency_rate') ?: 91.88;

        // INR becomes the base.
        DB::table('multi_currencies')->where('currency_code', 'INR')->update([
            'currency_rate' => 1.00,
            'is_default'    => 'yes',
        ]);

        // Other currencies — their rate becomes "1 INR equals X foreign units".
        // We compute by inverting the old INR-vs-USD rate. If old INR rate was
        // 91.88 (1 USD = 91.88 INR), then 1 INR = 1/91.88 ≈ 0.01088 USD.
        $foreign = [
            'USD' => 1 / $oldInrRate,        // ~0.01088
            'CAD' => 1 / $oldInrRate * 1.27, // CAD was 1.27x USD
            'NGN' => 1 / $oldInrRate * 417.35,
            'PHP' => 1 / $oldInrRate * 55.07,
            'BDT' => 1 / $oldInrRate * 80,
        ];
        foreach ($foreign as $code => $rate) {
            DB::table('multi_currencies')->where('currency_code', $code)->update([
                'currency_rate' => round($rate, 6),
                'is_default'    => 'no',
            ]);
        }

        // Update the global setting key for currency_code.
        DB::table('settings')->where('key', 'currency_code')->update(['value' => 'INR']);

        // Refresh seeded membership plan prices to round INR amounts. Only
        // rewrite the slugs we created — admin-edited plans are left alone.
        DB::table('membership_plans')->where('slug', 'coach-monthly')->update(['price' => 999.00]);
        DB::table('membership_plans')->where('slug', 'coach-yearly')->update(['price' => 9999.00]);
        DB::table('membership_plans')->where('slug', 'coach-lifetime')->update(['price' => 29999.00]);

        // Bust caches so the next request reads fresh values. allCurrencies is
        // a "rememberForever" key so it MUST be invalidated explicitly.
        \Cache::forget('setting');
        \Cache::forget('payment_setting');
        \Cache::forget('allCurrencies');
    }

    public function down(): void
    {
        // Best-effort revert to the legacy USD-base configuration.
        DB::table('multi_currencies')->where('currency_code', 'USD')->update([
            'currency_rate' => 1.00,
        ]);
        DB::table('multi_currencies')->where('currency_code', 'INR')->update([
            'currency_rate' => 91.88,
        ]);
        // Other rates not restored — admin can fix via the Currencies admin UI.
        \Cache::forget('setting');
    }
};
