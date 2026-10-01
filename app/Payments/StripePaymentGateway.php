<?php

namespace App\Payments;

use App\Models\Bid;
use App\Models\PaymentTransaction;
use RuntimeException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripePaymentGateway implements PaymentGatewayInterface
{
    public function configured(): bool
    {
        return filled(config('services.stripe.secret')) && filled(config('services.stripe.webhook_secret'));
    }

    public function name(): string
    {
        return 'stripe';
    }

    public function createCheckout(Bid $bid, string $successUrl, string $cancelUrl): CheckoutSession
    {
        $this->assertConfigured();
        $client = new StripeClient((string) config('services.stripe.secret'));
        $session = $client->checkout->sessions->create([
            'mode' => 'payment',
            'managed_payments' => ['enabled' => false],
            'payment_method_types' => ['card'],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => $bid->public_reference,
            'customer_email' => $bid->listing->contact_email,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($bid->currency),
                    'unit_amount' => $bid->amount_cents,
                    'product_data' => [
                        'name' => 'Marketing placement bid - '.$bid->listing->name,
                        'description' => 'Additional cumulative bid value. Placement can change when another listing bids.',
                    ],
                ],
            ]],
            'metadata' => ['bid_reference' => $bid->public_reference],
            'payment_intent_data' => [
                'description' => 'Marketing Bidding Central bid '.$bid->public_reference,
                'metadata' => ['bid_reference' => $bid->public_reference],
            ],
        ], ['idempotency_key' => 'checkout-'.$bid->public_reference]);

        if (! $session->url) {
            throw new RuntimeException('Stripe did not return a checkout URL.');
        }

        return new CheckoutSession($session->id, $session->url, [
            'mode' => $session->mode,
            'payment_status' => $session->payment_status,
            'amount_total' => $session->amount_total,
        ]);
    }

    public function parseWebhook(string $payload, string $signature): GatewayEvent
    {
        $this->assertConfigured();
        $event = Webhook::constructEvent($payload, $signature, (string) config('services.stripe.webhook_secret'));
        $object = $event->data->object;
        $metadata = $object->metadata ?? null;
        $bidReference = $metadata?->bid_reference ?? null;

        return new GatewayEvent(
            $event->id,
            $event->type,
            $bidReference,
            $object->payment_intent ?? $object->id ?? null,
            isset($object->amount_total) ? (int) $object->amount_total : (isset($object->amount) ? (int) $object->amount : null),
            (string) ($object->payment_status ?? $object->status ?? 'unknown'),
            ['livemode' => (bool) $event->livemode, 'object' => (string) ($object->object ?? 'unknown')],
        );
    }

    public function refund(PaymentTransaction $transaction, int $amountCents, string $reason): RefundResult
    {
        $this->assertConfigured();
        if (! $transaction->provider_transaction_id) {
            throw new RuntimeException('The payment transaction has no provider payment reference.');
        }

        $client = new StripeClient((string) config('services.stripe.secret'));
        $refund = $client->refunds->create([
            'payment_intent' => $transaction->provider_transaction_id,
            'amount' => $amountCents,
            'metadata' => ['reason' => mb_substr($reason, 0, 200), 'bid_reference' => $transaction->bid->public_reference],
        ], ['idempotency_key' => 'refund-'.$transaction->id.'-'.$transaction->refunded_cents.'-'.$amountCents]);

        return new RefundResult($refund->id, $refund->status, ['amount' => $refund->amount, 'currency' => $refund->currency]);
    }

    private function assertConfigured(): void
    {
        if (! $this->configured()) {
            throw new RuntimeException('Stripe credentials and webhook verification are not configured.');
        }
    }
}
