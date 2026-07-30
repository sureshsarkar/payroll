<?php

namespace Modules\Leave\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Leave\app\Models\LeaveType;

class LeaveDatabaseSeeder extends Seeder
{
    /**
     * Seed the standard leave types (idempotent — safe to re-run).
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Casual Leave',  'code' => 'CL',  'is_paid' => true,  'annual_quota' => 12, 'carry_forward' => false],
            ['name' => 'Sick Leave',    'code' => 'SL',  'is_paid' => true,  'annual_quota' => 10, 'carry_forward' => false],
            ['name' => 'Earned Leave',  'code' => 'EL',  'is_paid' => true,  'annual_quota' => 15, 'carry_forward' => true],
            ['name' => 'Loss of Pay',   'code' => 'LOP', 'is_paid' => false, 'annual_quota' => 0,  'carry_forward' => false],
        ];

        foreach ($types as $t) {
            LeaveType::updateOrCreate(['code' => $t['code']], $t + ['is_active' => true]);
        }
    }
}
