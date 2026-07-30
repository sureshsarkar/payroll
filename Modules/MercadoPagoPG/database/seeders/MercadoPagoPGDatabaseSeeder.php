<?php

namespace Modules\MercadoPagoPG\database\seeders;

use Illuminate\Database\Seeder;
use Modules\MercadoPagoPG\app\Models\MercadoPagoPG;

class MercadoPagoPGDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $payment_info = [
            'mercadopago_sandbox'  => true,
            'public_key'      => 'public_key',
            'access_token'   => 'access_token',
            'mercadopago_charge'   => 0.00,
            'mercadopago_status'   => 'inactive',
            'mercadopago_image'    => 'uploads/website-images/mercado-pago.png',
        ];

        foreach ($payment_info as $index => $payment_item) {
            $new_item = new MercadoPagoPG();
            $new_item->key = $index;
            $new_item->value = $payment_item;
            $new_item->save();
        }
    }
}
