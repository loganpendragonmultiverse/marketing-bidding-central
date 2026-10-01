<?php

namespace App\Payments;

readonly class RefundResult
{
    public function __construct(public string $id, public string $status, public array $safePayload = []) {}
}
