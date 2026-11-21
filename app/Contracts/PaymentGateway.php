<?php

namespace App\Contracts;

use App\Models\Order;
use App\Models\Payment;

interface PaymentGateway
{
    public function begin(Order $order): Payment;

    public function confirm(Payment $payment, string $outcome): void;

    public function refund(Payment $payment): void;
}
