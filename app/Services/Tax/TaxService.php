<?php

namespace App\Services\Tax;

use App\Models\TaxProfile;
use App\Models\TaxRate;

/**
 * The SINGLE source of truth for tax math (Phase 1, 2026-06-13).
 *
 * Everything tax-related — checkout, order snapshot, invoice, reports — goes
 * through here so the rules live in one place. Per-coach + opt-in:
 *
 *   • A coach with no profile, or is_enabled = false, or no usable rate →
 *     ZERO tax (charge_total == amount). So coaches who never configure tax
 *     are completely unaffected — the safe default.
 *
 *   • mode = 'exclusive'  → tax is ADDED on top:
 *         taxable = amount;  tax = amount * r;  charge_total = amount + tax
 *
 *   • mode = 'inclusive'  → the listed price already CONTAINS the tax:
 *         charge_total = amount;  taxable = amount / (1+r);  tax = amount - taxable
 *
 * Rate selection for a coach: an explicit per-course rate (when given and it
 * belongs to the coach + is active) wins; else the coach's default active rate;
 * else their first active rate; else no tax.
 *
 * Tenant isolation: a rate is only ever used if it belongs to the SAME coach,
 * so coach A's rate can never be applied to coach B.
 */
class TaxService
{
    /** In-request cache so a multi-item cart doesn't re-query per line. */
    private array $profileCache = [];

    /**
     * @return array{
     *   enabled: bool, mode: string, rate: float, rate_id: int|null, label: string,
     *   registration: string, taxable_amount: float, tax_amount: float, charge_total: float
     * }
     */
    public function computeForCoach(?int $coachId, float $amount, ?int $courseTaxRateId = null): array
    {
        $amount = round(max(0, $amount), 2);
        $none = $this->zero($amount);

        if (! $coachId || $amount <= 0) {
            return $none;
        }

        $profile = $this->profileFor($coachId);
        if (! $profile || ! $profile->is_enabled) {
            return $none;
        }

        $rate = $this->resolveRate($coachId, $courseTaxRateId);
        if (! $rate || (float) $rate->rate <= 0) {
            // Tax enabled but nothing usable to apply → no tax, but flag enabled
            // so callers can still stamp the coach's compliance identity if wanted.
            return array_merge($none, ['enabled' => true]);
        }

        $r = (float) $rate->rate / 100;

        if ($profile->mode === 'inclusive') {
            $taxable = round($amount / (1 + $r), 2);
            $tax = round($amount - $taxable, 2);
            $chargeTotal = $amount; // listed price already includes the tax
        } else { // exclusive
            $taxable = $amount;
            $tax = round($amount * $r, 2);
            $chargeTotal = round($amount + $tax, 2);
        }

        return [
            'enabled'        => true,
            'mode'           => $profile->mode,
            'rate'           => (float) $rate->rate,
            'rate_id'        => $rate->id,
            'label'          => $rate->name,
            'registration'   => $profile->registration_display,
            'taxable_amount' => $taxable,
            'tax_amount'     => $tax,
            'charge_total'   => $chargeTotal,
            // Per-component split (e.g. CGST + SGST) for the invoice, or [] for a
            // single rate. Amounts sum EXACTLY to tax_amount (last absorbs rounding).
            'components'     => $this->splitComponents($rate, $tax),
        ];
    }

    /**
     * Tax-INCLUSIVE split of a gross amount, regardless of the coach's mode.
     * Used for OFFLINE payments where the coach enters the total money received:
     * we derive base + tax from it ("received X, of which tax Y"). Returns the
     * same shape as computeForCoach; no tax when the coach has no enabled profile
     * or usable rate. (2026-07-11)
     */
    public function computeInclusive(?int $coachId, float $gross, ?int $courseTaxRateId = null): array
    {
        $gross = round(max(0, $gross), 2);
        $none = $this->zero($gross);
        if (! $coachId || $gross <= 0) {
            return $none;
        }
        $profile = $this->profileFor($coachId);
        if (! $profile || ! $profile->is_enabled) {
            return $none;
        }
        $rate = $this->resolveRate($coachId, $courseTaxRateId);
        if (! $rate || (float) $rate->rate <= 0) {
            return array_merge($none, ['enabled' => true]);
        }
        $r = (float) $rate->rate / 100;
        $taxable = round($gross / (1 + $r), 2);
        $tax = round($gross - $taxable, 2);

        return [
            'enabled'        => true,
            'mode'           => 'inclusive',
            'rate'           => (float) $rate->rate,
            'rate_id'        => $rate->id,
            'label'          => $rate->name,
            'registration'   => $profile->registration_display,
            'taxable_amount' => $taxable,
            'tax_amount'     => $tax,
            'charge_total'   => $gross,
            'components'     => $this->splitComponents($rate, $tax),
        ];
    }

    /**
     * Split the total tax into its named components, proportional to each
     * component's rate. The last component absorbs any rounding remainder so the
     * parts always sum to the total — no penny drift on the invoice.
     */
    private function splitComponents(TaxRate $rate, float $totalTax): array
    {
        $comps = is_array($rate->components) ? $rate->components : [];
        if (empty($comps) || $totalTax <= 0) {
            return [];
        }
        $sumRates = array_sum(array_map(fn ($c) => (float) ($c['rate'] ?? 0), $comps));
        if ($sumRates <= 0) {
            return [];
        }

        $out = [];
        $allocated = 0.0;
        $comps = array_values($comps);
        $n = count($comps);
        foreach ($comps as $i => $c) {
            $cr = (float) ($c['rate'] ?? 0);
            $amt = $i === $n - 1
                ? round($totalTax - $allocated, 2)        // last = remainder
                : round($totalTax * ($cr / $sumRates), 2);
            $allocated += $i === $n - 1 ? 0 : $amt;
            $out[] = ['name' => (string) ($c['name'] ?? 'Tax'), 'rate' => $cr, 'amount' => $amt];
        }

        return $out;
    }

    /**
     * Order-level tax for a checkout. We tax at the ORDER level (on the
     * post-coupon subtotal) keyed to the order's primary coach, because coupons
     * are order-level and a coach-domain cart is single-coach. Returns the order
     * snapshot + the amount the gateway should actually collect.
     *
     * @param  int|null  $coachId        the order's primary coach (item course instructor)
     * @param  float     $subtotal       post-coupon sum of effective prices (today's payable_amount)
     * @param  int|null  $courseTaxRateId optional per-course rate (single-item carts)
     * @return array{has_tax:bool, mode:string, rate:float, label:string, registration:string,
     *               taxable_amount:float, tax_amount:float, charge_total:float}
     */
    public function computeForOrder(?int $coachId, float $subtotal, ?int $courseTaxRateId = null): array
    {
        $r = $this->computeForCoach($coachId, $subtotal, $courseTaxRateId);

        return [
            'has_tax'        => $r['tax_amount'] > 0,
            'mode'           => $r['mode'],
            'rate'           => $r['rate'],
            'label'          => $r['label'],
            'registration'   => $r['registration'],
            'taxable_amount' => $r['taxable_amount'],
            'tax_amount'     => $r['tax_amount'],
            'charge_total'   => $r['charge_total'],
            'components'     => $r['components'] ?? [],
        ];
    }

    /** Does this coach have tax switched on? (cheap gate for previews/UI.) */
    public function isEnabledForCoach(?int $coachId): bool
    {
        if (! $coachId) {
            return false;
        }
        $p = $this->profileFor($coachId);

        return (bool) ($p && $p->is_enabled);
    }

    public function profileFor(int $coachId): ?TaxProfile
    {
        return $this->profileCache[$coachId]
            ??= TaxProfile::where('coach_id', $coachId)->first() ?: null;
    }

    private function resolveRate(int $coachId, ?int $courseTaxRateId): ?TaxRate
    {
        if ($courseTaxRateId) {
            $explicit = TaxRate::where('id', $courseTaxRateId)
                ->where('coach_id', $coachId)        // tenant guard — never another coach's rate
                ->where('status', 'active')->first();
            if ($explicit) {
                return $explicit;
            }
        }

        return TaxRate::where('coach_id', $coachId)->where('status', 'active')
            ->orderByDesc('is_default')->orderBy('id')->first();
    }

    private function zero(float $amount): array
    {
        return [
            'enabled'        => false,
            'mode'           => 'exclusive',
            'rate'           => 0.0,
            'rate_id'        => null,
            'label'          => '',
            'registration'   => '',
            'taxable_amount' => $amount,
            'tax_amount'     => 0.0,
            'charge_total'   => $amount,
            'components'     => [],
        ];
    }
}
