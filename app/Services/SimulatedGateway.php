<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Str;

final class SimulatedGateway implements PaymentGateway
{
    public function __construct(private readonly CommerceService $commerce) {}

    private function enabled(): void
    {
        abort_unless(app()->environment(['local', 'testing']) && config('commerce.gateway') === 'simulated', 503, 'The demo gateway is disabled. Configure a real payment provider before accepting orders.');
    }

    public function begin(Order $order): Payment
    {
        $this->enabled();

        return $order->payment()->firstOrCreate([], ['gateway' => 'simulated', 'reference' => (string) Str::uuid(), 'amount' => $order->total, 'currency' => $order->currency]);
    }

    public function confirm(Payment $payment, string $outcome): void
    {
        $this->enabled();
        $this->commerce->settle($payment, $outcome);
    }

    public function refund(Payment $payment): void
    {
        $this->enabled();
        $this->commerce->refund($payment);
    }
}
