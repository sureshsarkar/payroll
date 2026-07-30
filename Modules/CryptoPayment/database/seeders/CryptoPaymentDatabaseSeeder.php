<?php

namespace Modules\CryptoPayment\database\seeders;

use Illuminate\Database\Seeder;
use Modules\CryptoPayment\app\Models\CryptoPG;

class CryptoPaymentDatabaseSeeder extends Seeder {
    /**
     * Run the database seeds.
     */
    public function run(): void {
        $payment_info = [
            'crypto_sandbox'          => true,
            'crypto_token'            => 'crypto_token',
            'crypto_receive_currency' => 'USD',
            'crypto_charge'           => 0.00,
            'crypto_status'           => 'inactive',
            'crypto_image'            => 'uploads/website-images/coingate.webp',
        ];

        foreach ($payment_info as $index => $payment_item) {
            $new_item = new CryptoPG();
            $new_item->key = $index;
            $new_item->value = $payment_item;
            $new_item->save();
        }
    }
}
