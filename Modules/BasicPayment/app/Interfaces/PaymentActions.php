<?php

namespace Modules\BasicPayment\app\Interfaces;

interface PaymentActions
{
    public function processPayment($order, $orderType = 'order');
}
