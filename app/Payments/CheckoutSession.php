<?php

namespace App\Payments;

readonly class CheckoutSession
{
    public function __construct(public string $id, public string $url, public array $safePayload = []) {}
}
