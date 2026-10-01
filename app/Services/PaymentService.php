<?php

namespace App\Services;

use App\Models\Bid;
use App\Models\Listing;
use App\Models\PaymentTransaction;
use App\Models\WebhookEvent;
use App\Payments\GatewayEvent;
use App\Payments\PaymentGatewayInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
        private readonly BidService $bids,
        private readonly SettingsService $settings,
        private readonly CustomerAccountService $customers,
    ) {}

    public function available(): bool
    {
        return $this->settings->marketOpen() && $this->gateway->configured();
    }

    public function startCheckout(Listing $listing, int $amountCents, string $successUrl, string $cancelUrl): string
    {
        if (! $this->available()) {
            throw new RuntimeException('The market is not open for payments yet.');
        }
        $minimum = $listing->status === Listing::STATUS_ACTIVE ? $this->settings->minimumTopUpCents() : $this->settings->minimumBidCents();
        if ($amountCents < $minimum) {
            throw new RuntimeException('The bid is below the current minimum.');
        }

        $bid = $this->bids->createPending($listing, $amountCents, $this->gateway->name());
        $transaction = PaymentTransaction::query()->create([
            'bid_id' => $bid->id,
            'provider' => $this->gateway->name(),
            'idempotency_key' => (string) Str::uuid(),
            'amount_cents' => $amountCents,
            'currency' => $bid->currency,
            'status' => 'pending',
        ]);

        try {
            $session = $this->gateway->createCheckout($bid->load('listing'), $successUrl, $cancelUrl);
            $transaction->forceFill([
                'provider_transaction_id' => $session->id,
                'provider_payload' => $session->safePayload,
            ])->save();
            $bid->forceFill(['payment_reference' => $session->id])->save();

            return $session->url;
        } catch (Throwable $error) {
            $transaction->forceFill(['status' => 'failed', 'provider_payload' => ['error' => mb_substr($error->getMessage(), 0, 300)]])->save();
            $this->bids->fail($bid, $error->getMessage());
            throw $error;
        }
    }

    public function handleWebhook(string $payload, string $signature): void
    {
        $event = $this->gateway->parseWebhook($payload, $signature);
        $record = $this->claimWebhook($event, $payload);
        if (! $record) {
            return;
        }

        try {
            $this->processEvent($event);
            $record->forceFill(['status' => 'processed', 'processed_at' => now()])->save();
        } catch (Throwable $error) {
            $record->forceFill(['status' => 'failed', 'error' => mb_substr($error->getMessage(), 0, 1000), 'processed_at' => now()])->save();
            throw $error;
        }
    }

    private function claimWebhook(GatewayEvent $event, string $payload): ?WebhookEvent
    {
        try {
            return WebhookEvent::query()->create([
                'provider' => $this->gateway->name(),
                'provider_event_id' => $event->id,
                'event_type' => $event->type,
                'payload_hash' => hash('sha256', $payload),
                'status' => 'received',
            ]);
        } catch (QueryException $error) {
            if (str_contains(strtolower($error->getMessage()), 'unique')) {
                $existing = WebhookEvent::query()
                    ->where('provider', $this->gateway->name())
                    ->where('provider_event_id', $event->id)
                    ->first();

                if (! $existing || $existing->status !== 'failed') {
                    return null;
                }

                $existing->forceFill([
                    'payload_hash' => hash('sha256', $payload),
                    'status' => 'received',
                    'error' => null,
                    'processed_at' => null,
                ])->save();

                return $existing;
            }
            throw $error;
        }
    }

    private function processEvent(GatewayEvent $event): void
    {
        if (! $event->bidReference) {
            return;
        }

        $bid = Bid::query()->where('public_reference', $event->bidReference)->first();
        if (! $bid && in_array($event->type, ['checkout.session.expired', 'payment_intent.payment_failed'], true)) {
            return;
        }
        if (! $bid) {
            throw new RuntimeException('The payment event references an unknown bid.');
        }
        $transaction = $bid->transaction()->firstOrFail();

        if ($event->type === 'checkout.session.completed' && $event->status === 'paid') {
            if ($event->amountCents === null) {
                throw new RuntimeException('The completed checkout did not include an amount.');
            }
            $firstPayment = $bid->listing()->value('status') === Listing::STATUS_PENDING;
            $this->bids->confirm($bid, (string) $event->paymentReference, $event->amountCents);
            $transaction->forceFill([
                'provider_transaction_id' => $event->paymentReference,
                'status' => 'confirmed',
                'provider_payload' => $event->safePayload,
            ])->save();
            if ($firstPayment) {
                $this->customers->provisionFor($bid->listing()->firstOrFail());
            }

            return;
        }

        if (in_array($event->type, ['checkout.session.expired', 'payment_intent.payment_failed'], true)) {
            $this->bids->fail($bid, 'Provider event: '.$event->type);
            $transaction->forceFill(['status' => 'failed', 'provider_payload' => $event->safePayload])->save();
        }
    }
}
