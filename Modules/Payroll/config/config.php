<?php

return [
    'name' => 'Payroll',

    /*
    |--------------------------------------------------------------------------
    | Establishment / factory header
    |--------------------------------------------------------------------------
    | Printed on the statutory wage register (Delhi Form IV). Set these in .env
    | so the register shows the correct registered employer name, address and
    | statutory registration numbers.
    */
    'establishment' => [
        'name'    => env('PAYROLL_ESTABLISHMENT_NAME', config('app.name')),
        'address' => env('PAYROLL_ESTABLISHMENT_ADDRESS', ''),
        'pf_no'   => env('PAYROLL_ESTABLISHMENT_PF_NO', ''),
        'esi_no'  => env('PAYROLL_ESTABLISHMENT_ESI_NO', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Coach / HR panel presentation
    |--------------------------------------------------------------------------
    | During the LMS→Payroll conversion the coach panel is repurposed as an
    | HR/Payroll workspace. The legacy LMS sidebar groups (Academic, People,
    | Sales & Operations, Communication) are hidden by default. Set
    | PAYROLL_SHOW_LMS_COACH_MENUS=true to bring them back.
    */
    'coach' => [
        'show_lms_menus' => env('PAYROLL_SHOW_LMS_COACH_MENUS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Statutory deduction defaults (India)
    |--------------------------------------------------------------------------
    | Rates are configurable — Super Admin can override at runtime later. These
    | ship as sensible India defaults so payroll works out of the box.
    | Toggle `enabled` per head to switch a deduction off company-wide.
    */
    'statutory' => [

        // Employees' Provident Fund — 12% of Basic, wage ceiling ₹15,000/month.
        'pf' => [
            'enabled'        => true,
            'employee_rate'  => 12.0,   // % of PF wage
            'wage_ceiling'   => 15000,  // cap Basic at this for PF
            'cap_to_ceiling' => true,
        ],

        // Employees' State Insurance — 0.75% of gross, only if gross <= ₹21,000.
        'esic' => [
            'enabled'          => true,
            'employee_rate'    => 0.75,  // % of gross
            'eligibility_gross'=> 21000, // applies only when gross <= this
        ],

        // Professional Tax — state slab (Maharashtra-style default).
        'professional_tax' => [
            'enabled' => true,
            'slabs'   => [
                // [up_to_gross_monthly, amount]  (null = and above)
                [7500, 0],
                [10000, 175],
                [null, 200],   // ₹300 in February handled by callers if needed
            ],
        ],

        // TDS — simplified flat effective rate on annualised taxable pay above
        // the basic exemption. Real slab engine can replace this later.
        'tds' => [
            'enabled'            => false, // off by default; enable per company
            'annual_exemption'   => 250000,
            'flat_effective_rate'=> 5.0,   // % applied above exemption
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Attendance shape used by the Form XI wage slip
    |--------------------------------------------------------------------------
    | The slip splits the month into OT hours and weekly-offs, neither of which
    | the attendance table stores directly. `standard_day_minutes` is the shift
    | length above which worked time counts as overtime; `weekly_off_day` is the
    | rest day (Carbon day-of-week: 0 = Sunday).
    */
    'attendance' => [
        'standard_day_minutes' => env('PAYROLL_STANDARD_DAY_MINUTES', 480),
        'weekly_off_day'       => env('PAYROLL_WEEKLY_OFF_DAY', 0),
    ],

    // Payslip / run behaviour
    'payslip' => [
        'currency_symbol' => '₹',
        'storage_disk'    => 'public',
        'storage_dir'     => 'payslips',

        /*
        | Print the Hindi half of every Form XI label alongside the English one.
        | The statutory register is bilingual, so this is on by default; turn it
        | off for an English-only establishment.
        */
        'bilingual' => env('PAYROLL_PAYSLIP_BILINGUAL', true),
    ],
];
