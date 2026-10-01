<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'bid_id', 'provider', 'provider_transaction_id', 'idempotency_key',
        'amount_cents', 'refunded_cents', 'currency', 'status', 'provider_payload',
    ];

    protected $hidden = ['provider_payload'];

    protected function casts(): array
    {
        return ['refunded_cents' => 'integer', 'amount_cents' => 'integer', 'provider_payload' => 'array'];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Payment transaction records cannot be deleted.'));
    }

    public function bid(): BelongsTo
    {
        return $this->belongsTo(Bid::class);
    }
}
