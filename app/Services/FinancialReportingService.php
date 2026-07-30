<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Modules\Order\app\Models\Order;

/**
 * Authoritative financial reporting layer (Release 1 — 2026-07-17).
 *
 * ONE source of truth for aggregate money reporting so the Super Admin dashboard,
 * sales chart, Coach Billing and per-coach commission surfaces all reconcile with
 * the real money-movement layer (Modules\Order\app\Models\OrderItem::netPaid /
 * commissionAmount / coachPayout — the same values credited to coach wallets in
 * PaymentFulfilmentService).
 *
 * Metric definitions (kept strictly separate):
 *   Gross Sales           = SUM(order_items.price)                 (raw catalogue value)
 *   Coupon Discount       = orders.coupon_discount_amount
 *   Net Customer Payment  = orders.payable_amount                  (= gross − coupon, pre-tax base)
 *   Platform Commission   = SUM(net_paid × commission_rate/100)    (== SUM OrderItem::commissionAmount)
 *   Coach Earnings        = net_paid − platform_commission         (== SUM OrderItem::coachPayout = wallet credit)
 *   Tax / Gateway Charge  = orders.tax_amount / gateway_charge     (NOT part of the commission base)
 *
 * KEY IDENTITY: orders.payable_amount == SUM of the order's items' OrderItem::netPaid()
 *   (gross − coupon, allocated per item by price). Therefore the efficient order-level
 *   aggregate  SUM(payable_amount × commission_rate/100)  equals the per-item model sum.
 *   The reconcile() method proves this at runtime for any set of orders.
 *
 * Inclusion rule: only payment_status = 'paid' contributes to revenue/commission/
 * earnings/payout. Failed / cancelled / declined / pending are excluded.
 *
 * Currency (AUD-028): money of DIFFERENT currencies must NEVER be summed into one
 * total. Each paid order carries orders.payable_currency (the transaction currency)
 * and orders.conversion_rate (the rate captured AT transaction time). All figures are
 * therefore reported *grouped by their original transaction currency* — see
 * commissionByCurrency() / coachLifetimeByCurrency() / coachRecentByCurrency().
 *   - The stored historical conversion_rate is preserved and never overwritten; the
 *     current session exchange rate is NEVER applied to a historical transaction.
 *   - Missing / blank / non-ISO currency codes are collected into a single UNKNOWN
 *     exception group (never silently folded into the base currency).
 *   - The plain scalar helpers (platformCommission/coachLifetime/...) accept an
 *     optional $currency filter so a caller can scope a single-currency figure; when
 *     no filter is given they operate over one deployment currency (the common case —
 *     the demo/prod data is single-currency INR) and callers must render them with the
 *     correct currency via primaryCurrency(). Use the *ByCurrency() methods whenever a
 *     deployment may hold more than one currency (isMultiCurrency()).
 */
class FinancialReportingService
{
    /** Payment status that counts as settled revenue. */
    public const PAID = 'paid';

    /** Grouping key for orders with a missing / invalid currency code. */
    public const UNKNOWN_CURRENCY = 'UNKNOWN';

    /** Order-level platform-commission SQL (== SUM OrderItem::commissionAmount). */
    public function orderCommissionExpr(): string
    {
        // payable_amount is already the post-coupon, pre-tax base. Do NOT add
        // gateway_charge and do NOT subtract coupon again (both were the AUD-002 bug).
        return 'payable_amount * (commission_rate / 100)';
    }

    /** Net customer payment base (post-coupon, pre-tax) — for the sales trend. */
    public function orderNetRevenueExpr(): string
    {
        return 'payable_amount';
    }

    /** Base query: paid orders only (excludes failed/cancelled/declined/pending). */
    public function paidOrders()
    {
        return Order::where('payment_status', self::PAID);
    }

    /**
     * Platform commission over an optional [from,to] window. commission_rate>0 is a
     * harmless micro-filter (0-rate orders contribute 0 anyway).
     *
     * $currency (AUD-028): when given, restrict to that ONE transaction currency so the
     * scalar is a within-currency figure (never a cross-currency sum). null = the whole
     * deployment (correct only when the deployment is single-currency — guard with
     * isMultiCurrency()).
     */
    public function platformCommission($from = null, $to = null, ?string $currency = null): float
    {
        $q = $this->paidOrders()->where('commission_rate', '>', 0);
        if ($from && $to) {
            $q->whereBetween('created_at', [$from, $to]);
        }
        $this->scopeCurrency($q, $currency);
        return (float) $q->selectRaw('SUM(' . $this->orderCommissionExpr() . ') s')->value('s');
    }

    /**
     * Constrain a query builder to one transaction currency (AUD-028). The UNKNOWN
     * exception group matches rows whose payable_currency is NULL/blank/non-ISO.
     */
    protected function scopeCurrency($q, ?string $currency): void
    {
        $this->currencyWhere($q, $currency, 'payable_currency');
    }

    /** Same as scopeCurrency but for a joined query where orders is aliased `o`. */
    protected function scopeCurrencyOrders($q, ?string $currency): void
    {
        $this->currencyWhere($q, $currency, 'o.payable_currency');
    }

    /** Apply a single-currency (or UNKNOWN exception) filter on the given column. */
    protected function currencyWhere($q, ?string $currency, string $col): void
    {
        if ($currency === null) {
            return;
        }
        if ($this->normalizeCurrency($currency) === self::UNKNOWN_CURRENCY) {
            $q->where(function ($w) use ($col) {
                $w->whereNull($col)
                  ->orWhereRaw("TRIM(COALESCE($col,'')) = ''")
                  ->orWhereRaw("$col NOT REGEXP '^[A-Za-z]{3}$'");
            });
        } else {
            $q->whereRaw("UPPER(TRIM($col)) = ?", [$this->normalizeCurrency($currency)]);
        }
    }

    /**
     * Normalize a raw payable_currency into a grouping key: a valid 3-letter ISO-style
     * code (upper-cased) or the UNKNOWN exception bucket. Never guesses a real currency.
     */
    public function normalizeCurrency(?string $code): string
    {
        $code = strtoupper(trim((string) $code));
        return preg_match('/^[A-Z]{3}$/', $code) === 1 ? $code : self::UNKNOWN_CURRENCY;
    }

    /** The deployment's base/primary currency (setting.currency_code, else config, else INR). */
    public function primaryCurrency(): string
    {
        $code = cache('setting')?->currency_code
            ?? config('app.currency')
            ?? 'INR';
        return $this->normalizeCurrency($code);
    }

    /** Distinct normalized transaction currencies among paid orders (UNKNOWN last). */
    public function currencies($from = null, $to = null): array
    {
        return array_column($this->commissionByCurrency($from, $to), 'currency');
    }

    /** True when paid orders span more than one distinct transaction currency. */
    public function isMultiCurrency($from = null, $to = null): bool
    {
        return count($this->currencies($from, $to)) > 1;
    }

    /**
     * Platform commission + net revenue + order count, GROUPED by original transaction
     * currency (AUD-028). Currencies are never summed together; NULL/invalid currencies
     * form a single UNKNOWN exception group. Sorted: real currencies by commission desc,
     * UNKNOWN always last.
     *
     * @return array<int,array{currency:string,orders:int,net_revenue:float,platform_commission:float,is_exception:bool}>
     */
    public function commissionByCurrency($from = null, $to = null): array
    {
        $q = $this->paidOrders();
        if ($from && $to) {
            $q->whereBetween('created_at', [$from, $to]);
        }
        $rows = $q->selectRaw(
            'payable_currency as raw_currency, '
            . 'COUNT(*) as orders, '
            . 'SUM(' . $this->orderNetRevenueExpr() . ') as net_revenue, '
            . 'SUM(' . $this->orderCommissionExpr() . ') as platform_commission'
        )->groupBy('payable_currency')->get();

        $grouped = [];
        foreach ($rows as $r) {
            $key = $this->normalizeCurrency($r->raw_currency);
            $grouped[$key] ??= [
                'currency'            => $key,
                'orders'              => 0,
                'net_revenue'         => 0.0,
                'platform_commission' => 0.0,
                'is_exception'        => $key === self::UNKNOWN_CURRENCY,
            ];
            $grouped[$key]['orders']              += (int) $r->orders;
            $grouped[$key]['net_revenue']         += (float) $r->net_revenue;
            $grouped[$key]['platform_commission'] += (float) $r->platform_commission;
        }

        foreach ($grouped as &$g) {
            $g['net_revenue']         = round($g['net_revenue'], 2);
            $g['platform_commission'] = round($g['platform_commission'], 2);
        }
        unset($g);

        uasort($grouped, function ($a, $b) {
            if ($a['is_exception'] !== $b['is_exception']) {
                return $a['is_exception'] ? 1 : -1; // UNKNOWN last
            }
            return $b['platform_commission'] <=> $a['platform_commission'];
        });

        return array_values($grouped);
    }

    /**
     * Per-item net-paid SQL fragment (mirrors OrderItem::netPaid): the item price
     * minus this item's proportional share of the WHOLE-ORDER coupon discount,
     * allocated by gross price. Requires the joined `og` (per-order gross_total)
     * sub-query. Guarded for zero gross and clamped at >= 0.
     */
    public function netPaidSql(string $oi = 'oi', string $o = 'o', string $og = 'og'): string
    {
        return "GREATEST(0, {$oi}.price - COALESCE("
             . "COALESCE({$o}.coupon_discount_amount,0) * ({$oi}.price / NULLIF({$og}.gross_total,0)), 0))";
    }

    /** Sub-query of per-order gross totals (SUM of all items' price), for coupon allocation. */
    protected function orderGrossSub()
    {
        return DB::table('order_items')
            ->select('order_id', DB::raw('SUM(price) as gross_total'))
            ->groupBy('order_id');
    }

    /**
     * Lifetime net settled figures for ONE coach's course sales — the values that
     * actually reconcile with the coach's wallet.
     *
     * @return array{gross:float, gross_catalogue:float, platform_commission:float, coach_revenue:float}
     */
    public function coachLifetime(int $coachId, ?string $currency = null): array
    {
        $net = $this->netPaidSql();
        $rate = 'COALESCE(oi.commission_rate, o.commission_rate, 0)';

        $q = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('courses as c', 'c.id', '=', 'oi.course_id')
            ->joinSub($this->orderGrossSub(), 'og', 'og.order_id', '=', 'oi.order_id')
            ->where('o.payment_status', self::PAID)
            ->where('c.instructor_id', $coachId);
        $this->scopeCurrencyOrders($q, $currency);
        $agg = $q
            ->selectRaw("COALESCE(SUM($net),0) as net_gross")
            ->selectRaw("COALESCE(SUM(oi.price),0) as gross_catalogue")
            ->selectRaw("COALESCE(SUM($net * $rate / 100),0) as platform")
            ->first();

        $net_gross = round((float) ($agg->net_gross ?? 0), 2);
        $platform  = round((float) ($agg->platform ?? 0), 2);

        return [
            // 'gross' is the NET settled revenue (post-coupon) so that
            // coach_revenue = gross − platform reconciles with the wallet credit.
            'gross'               => $net_gross,
            'gross_catalogue'     => round((float) ($agg->gross_catalogue ?? 0), 2),
            'platform_commission' => $platform,
            'coach_revenue'       => round($net_gross - $platform, 2),
        ];
    }

    /**
     * Recent (N-day) order count + net settled revenue for a coach, scoped by the
     * course's instructor_id (NOT orders.seller_id, which the main web checkout
     * never sets — the AUD-013 bug that omitted every gateway sale).
     *
     * @return array{orders_30d:int, gross_30d:float}
     */
    public function coachRecentNet(int $coachId, int $days = 30, ?string $currency = null): array
    {
        $net  = $this->netPaidSql();
        $from = now()->subDays($days);

        $q = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('courses as c', 'c.id', '=', 'oi.course_id')
            ->joinSub($this->orderGrossSub(), 'og', 'og.order_id', '=', 'oi.order_id')
            ->where('o.payment_status', self::PAID)
            ->where('c.instructor_id', $coachId)
            ->where('o.created_at', '>=', $from);
        $this->scopeCurrencyOrders($q, $currency);
        $row = $q
            ->selectRaw('COUNT(DISTINCT o.id) as orders_30d')
            ->selectRaw("COALESCE(SUM($net),0) as gross_30d")
            ->first();

        return [
            'orders_30d' => (int) ($row->orders_30d ?? 0),
            'gross_30d'  => round((float) ($row->gross_30d ?? 0), 2),
        ];
    }

    /**
     * One coach's lifetime figures GROUPED by transaction currency (AUD-028). Each row
     * is the same shape as coachLifetime() plus its currency + is_exception flag.
     *
     * @return array<int,array{currency:string,gross:float,gross_catalogue:float,platform_commission:float,coach_revenue:float,is_exception:bool}>
     */
    public function coachLifetimeByCurrency(int $coachId): array
    {
        $net  = $this->netPaidSql();
        $rate = 'COALESCE(oi.commission_rate, o.commission_rate, 0)';

        $rows = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('courses as c', 'c.id', '=', 'oi.course_id')
            ->joinSub($this->orderGrossSub(), 'og', 'og.order_id', '=', 'oi.order_id')
            ->where('o.payment_status', self::PAID)
            ->where('c.instructor_id', $coachId)
            ->selectRaw('o.payable_currency as raw_currency')
            ->selectRaw("COALESCE(SUM($net),0) as net_gross")
            ->selectRaw('COALESCE(SUM(oi.price),0) as gross_catalogue')
            ->selectRaw("COALESCE(SUM($net * $rate / 100),0) as platform")
            ->groupBy('o.payable_currency')
            ->get();

        return $this->foldCurrencyRows($rows, function ($r) {
            $net      = round((float) $r->net_gross, 2);
            $platform = round((float) $r->platform, 2);
            return [
                'gross'               => $net,
                'gross_catalogue'     => round((float) $r->gross_catalogue, 2),
                'platform_commission' => $platform,
                'coach_revenue'       => round($net - $platform, 2),
            ];
        });
    }

    /** One coach's recent (N-day) order count + net revenue, grouped by currency (AUD-028). */
    public function coachRecentByCurrency(int $coachId, int $days = 30): array
    {
        $net  = $this->netPaidSql();
        $from = now()->subDays($days);

        $rows = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('courses as c', 'c.id', '=', 'oi.course_id')
            ->joinSub($this->orderGrossSub(), 'og', 'og.order_id', '=', 'oi.order_id')
            ->where('o.payment_status', self::PAID)
            ->where('c.instructor_id', $coachId)
            ->where('o.created_at', '>=', $from)
            ->selectRaw('o.payable_currency as raw_currency')
            ->selectRaw('COUNT(DISTINCT o.id) as orders_30d')
            ->selectRaw("COALESCE(SUM($net),0) as gross_30d")
            ->groupBy('o.payable_currency')
            ->get();

        return $this->foldCurrencyRows($rows, fn ($r) => [
            'orders_30d' => (int) $r->orders_30d,
            'gross_30d'  => round((float) $r->gross_30d, 2),
        ]);
    }

    /**
     * Fold DB rows carrying a `raw_currency` column into normalized currency groups
     * (merging NULL/invalid into UNKNOWN, summing numeric fields), UNKNOWN sorted last.
     */
    protected function foldCurrencyRows($rows, callable $shape): array
    {
        $grouped = [];
        foreach ($rows as $r) {
            $key = $this->normalizeCurrency($r->raw_currency ?? null);
            $vals = $shape($r);
            if (!isset($grouped[$key])) {
                $grouped[$key] = array_merge(['currency' => $key, 'is_exception' => $key === self::UNKNOWN_CURRENCY], $vals);
            } else {
                foreach ($vals as $k => $v) {
                    $grouped[$key][$k] = round(((float) $grouped[$key][$k]) + (float) $v, 2);
                }
            }
        }
        uasort($grouped, fn ($a, $b) => $a['is_exception'] <=> $b['is_exception']);
        return array_values($grouped);
    }

    /**
     * Per-currency breakdown of net revenue + commission. Retained name; now delegates
     * to commissionByCurrency() so NULL/invalid currencies land in the UNKNOWN exception
     * group instead of being folded into the base currency.
     */
    public function byCurrency($from = null, $to = null): array
    {
        return $this->commissionByCurrency($from, $to);
    }

    /**
     * Runtime reconciliation: for each order, compare the order-level SQL commission
     * (payable_amount × rate/100) against the authoritative per-item model sum
     * (SUM OrderItem::commissionAmount). Proves the aggregate equals the money layer.
     *
     * @return array<int,array{order_id:int,sql:float,model:float,diff:float}>
     */
    public function reconcile(array $orderIds): array
    {
        $out = [];
        $orders = Order::with('orderItems')->whereIn('id', $orderIds)->get();
        foreach ($orders as $order) {
            $rate  = (float) ($order->commission_rate ?? 0);
            $sql   = round((float) ($order->payable_amount ?? 0) * $rate / 100, 2);
            $model = round($order->orderItems->sum(fn ($i) => $i->commissionAmount($rate)), 2);
            $out[] = [
                'order_id' => (int) $order->id,
                'sql'      => $sql,
                'model'    => $model,
                'diff'     => round($sql - $model, 2),
            ];
        }
        return $out;
    }
}
