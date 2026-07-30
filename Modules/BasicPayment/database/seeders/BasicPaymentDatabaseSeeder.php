<?php

namespace Modules\BasicPayment\database\seeders;

use Illuminate\Database\Seeder;
use Modules\BkashPG\database\seeders\BkashPGDatabaseSeeder;
use Modules\CryptoPayment\database\seeders\CryptoPaymentDatabaseSeeder;
use Modules\MercadoPagoPG\database\seeders\MercadoPagoPGDatabaseSeeder;

class BasicPaymentDatabaseSeeder extends Seeder {
    /**
     * Run the database seeds.
     */
    public function run(): void {
        $this->call([
            BasicPaymentInfoSeeder::class,
            PaymentGatewaySeeder::class,
            BkashPGDatabaseSeeder::class,
            MercadoPagoPGDatabaseSeeder::class,
            CryptoPaymentDatabaseSeeder::class,
        ]);
    }
}
