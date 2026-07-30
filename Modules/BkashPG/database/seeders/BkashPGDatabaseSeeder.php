<?php

namespace Modules\BkashPG\database\seeders;

use Illuminate\Database\Seeder;
use Modules\BkashPG\app\Models\BkashPGModel;

class BkashPGDatabaseSeeder extends Seeder {
    /**
     * Run the database seeds.
     */
    public function run(): void {
        $payment_info = [
            'bkash_sandbox'  => true,
            'bkash_key'      => 'bkash_key',
            'bkash_secret'   => 'bkash_secret',
            'bkash_username' => 'bkash_username',
            'bkash_password' => 'bkash_password',
            'bkash_status'   => 'inactive',
            'bkash_charge'   => 0.00,
            'bkash_image'    => 'uploads/website-images/bkash.png',
        ];

        foreach ($payment_info as $index => $payment_item) {
            $new_item = new BkashPGModel();
            $new_item->key = $index;
            $new_item->value = $payment_item;
            $new_item->save();
        }
    }
}
