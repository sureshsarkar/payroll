<?php

namespace Modules\Payroll\app\Services;

/**
 * Computes statutory deductions (India: PF, ESIC, Professional Tax, TDS) from a
 * monthly Basic + Gross. All rates come from config('payroll.statutory') so
 * Super Admin can tune them without code changes.
 *
 * Every method returns a list of deduction lines:
 *   ['name' => 'PF', 'code' => 'PF', 'amount' => 1800.0, 'statutory' => true]
 */
class StatutoryCalculator
{
    /** @return array<int, array{name:string,code:string,amount:float,statutory:bool}> */
    public function deductions(float $basic, float $gross): array
    {
        $cfg = config('payroll.statutory', []);
        $lines = [];

        if (($cfg['pf']['enabled'] ?? false) && ($pf = $this->pf($basic, $cfg['pf'])) > 0) {
            $lines[] = $this->line('Provident Fund (PF)', 'PF', $pf);
        }
        if (($cfg['esic']['enabled'] ?? false) && ($esic = $this->esic($gross, $cfg['esic'])) > 0) {
            $lines[] = $this->line('ESIC', 'ESIC', $esic);
        }
        if (($cfg['professional_tax']['enabled'] ?? false) && ($pt = $this->professionalTax($gross, $cfg['professional_tax'])) > 0) {
            $lines[] = $this->line('Professional Tax', 'PT', $pt);
        }
        if (($cfg['tds']['enabled'] ?? false) && ($tds = $this->tds($gross, $cfg['tds'])) > 0) {
            $lines[] = $this->line('TDS', 'TDS', $tds);
        }

        return $lines;
    }

    public function pf(float $basic, array $cfg): float
    {
        $wage = $basic;
        if (($cfg['cap_to_ceiling'] ?? false) && isset($cfg['wage_ceiling'])) {
            $wage = min($basic, (float) $cfg['wage_ceiling']);
        }

        return round($wage * ((float) ($cfg['employee_rate'] ?? 0)) / 100, 2);
    }

    public function esic(float $gross, array $cfg): float
    {
        if ($gross > (float) ($cfg['eligibility_gross'] ?? 0)) {
            return 0.0;
        }

        return round($gross * ((float) ($cfg['employee_rate'] ?? 0)) / 100, 2);
    }

    public function professionalTax(float $gross, array $cfg): float
    {
        foreach (($cfg['slabs'] ?? []) as [$upTo, $amount]) {
            if ($upTo === null || $gross <= (float) $upTo) {
                return round((float) $amount, 2);
            }
        }

        return 0.0;
    }

    public function tds(float $gross, array $cfg): float
    {
        $annual = $gross * 12;
        $taxable = $annual - (float) ($cfg['annual_exemption'] ?? 0);
        if ($taxable <= 0) {
            return 0.0;
        }

        return round(($taxable * ((float) ($cfg['flat_effective_rate'] ?? 0)) / 100) / 12, 2);
    }

    private function line(string $name, string $code, float $amount): array
    {
        return ['name' => $name, 'code' => $code, 'amount' => $amount, 'statutory' => true];
    }
}
