<?php

namespace App\Payments;

readonly class GatewayEvent
{
    public function __construct(
        public string $id,
        public string $type,
        public ?string $bidReference,
        public ?string $paymentReference,
        public ?int $amountCents,
        public string $status,
        public array $safePayload = [],
    ) {}
}
