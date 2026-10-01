<?php

namespace App\Payments;

use App\Models\Bid;
use App\Models\PaymentTransaction;
use RuntimeException;

class NullPaymentGateway implements PaymentGatewayInterface
{
    public function configured(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'disabled';
    }

    public function createCheckout(Bid $bid, string $successUrl, string $cancelUrl): CheckoutSession
    {
        throw new RuntimeException('Payment checkout is not configured.');
    }

    public function parseWebhook(string $payload, string $signature): GatewayEvent
    {
        throw new RuntimeException('Payment webhooks are not configured.');
    }

    public function refund(PaymentTransaction $transaction, int $amountCents, string $reason): RefundResult
    {
        throw new RuntimeException('Provider refunds are not configured.');
    }
}
