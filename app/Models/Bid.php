<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class Bid extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'listing_id', 'user_id', 'public_reference', 'direction', 'amount_cents',
        'signed_delta_cents', 'previous_total_cents', 'new_total_cents', 'currency',
        'payment_provider', 'payment_reference', 'payment_status', 'ranking_before',
        'ranking_after', 'notes', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'signed_delta_cents' => 'integer',
            'previous_total_cents' => 'integer',
            'new_total_cents' => 'integer',
            'ranking_before' => 'integer',
            'ranking_after' => 'integer',
            'confirmed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Bid ledger records cannot be deleted.'));
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(PaymentTransaction::class);
    }
}
