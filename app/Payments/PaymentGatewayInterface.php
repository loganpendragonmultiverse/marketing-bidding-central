<?php

namespace App\Payments;

use App\Models\Bid;
use App\Models\PaymentTransaction;

interface PaymentGatewayInterface
{
    public function configured(): bool;

    public function name(): string;

    public function createCheckout(Bid $bid, string $successUrl, string $cancelUrl): CheckoutSession;

    public function parseWebhook(string $payload, string $signature): GatewayEvent;

    public function refund(PaymentTransaction $transaction, int $amountCents, string $reason): RefundResult;
}
