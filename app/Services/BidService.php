<?php

namespace App\Services;

use App\Models\ActivityEvent;
use App\Models\Bid;
use App\Models\Listing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class BidService
{
    public function __construct(private readonly RankingService $rankings) {}

    public function createPending(Listing $listing, int $amountCents, string $provider): Bid
    {
        if (! in_array($listing->status, [Listing::STATUS_PENDING, Listing::STATUS_ACTIVE], true)) {
            throw new RuntimeException('This listing is not available for bidding.');
        }

        $previousTotal = (int) ($listing->total_bid_cents ?? 0);

        return Bid::query()->create([
            'listing_id' => $listing->id,
            'public_reference' => (string) Str::uuid(),
            'direction' => 'credit',
            'amount_cents' => $amountCents,
            'signed_delta_cents' => $amountCents,
            'previous_total_cents' => $previousTotal,
            'new_total_cents' => $previousTotal + $amountCents,
            'currency' => config('marketplace.currency'),
            'payment_provider' => $provider,
            'payment_status' => Bid::STATUS_PENDING,
            'ranking_before' => $this->rankings->globalRank($listing),
        ]);
    }

    public function confirm(Bid $bid, string $paymentReference, int $paidAmountCents): Bid
    {
        return DB::transaction(function () use ($bid, $paymentReference, $paidAmountCents): Bid {
            $lockedBid = Bid::query()->lockForUpdate()->findOrFail($bid->id);
            if ($lockedBid->payment_status === Bid::STATUS_CONFIRMED) {
                return $lockedBid;
            }
            if ($lockedBid->payment_status !== Bid::STATUS_PENDING) {
                throw new RuntimeException('Only pending bids can be confirmed.');
            }
            if ($paidAmountCents !== $lockedBid->amount_cents) {
                throw new RuntimeException('The confirmed payment amount does not match the bid ledger.');
            }

            $listing = Listing::query()->lockForUpdate()->findOrFail($lockedBid->listing_id);
            if (! in_array($listing->status, [Listing::STATUS_PENDING, Listing::STATUS_ACTIVE], true)) {
                throw new RuntimeException('The listing is no longer active.');
            }

            $beforeRank = $this->rankings->globalRank($listing);
            $previous = (int) $listing->total_bid_cents;
            $newTotal = $previous + $lockedBid->amount_cents;
            $reachedAt = now();

            $listing->forceFill([
                'status' => Listing::STATUS_ACTIVE,
                'total_bid_cents' => $newTotal,
                'last_bid_at' => $reachedAt,
            ])->save();
            $listing->refresh();
            $afterRank = $this->rankings->globalRank($listing);

            $lockedBid->forceFill([
                'previous_total_cents' => $previous,
                'new_total_cents' => $newTotal,
                'payment_reference' => $paymentReference,
                'payment_status' => Bid::STATUS_CONFIRMED,
                'ranking_before' => $beforeRank,
                'ranking_after' => $afterRank,
                'confirmed_at' => $reachedAt,
            ])->save();

            ActivityEvent::query()->create([
                'listing_id' => $listing->id,
                'type' => 'bid_confirmed',
                'public_message' => $this->activityMessage($listing->name, $lockedBid->amount_cents, $beforeRank, $afterRank),
                'metadata' => ['ranking_before' => $beforeRank, 'ranking_after' => $afterRank, 'amount_cents' => $lockedBid->amount_cents],
                'visible' => true,
                'occurred_at' => $reachedAt,
            ]);

            return $lockedBid->fresh();
        }, 3);
    }

    public function fail(Bid $bid, string $reason): void
    {
        DB::transaction(function () use ($bid, $reason): void {
            $locked = Bid::query()->lockForUpdate()->findOrFail($bid->id);
            if ($locked->payment_status === Bid::STATUS_PENDING) {
                $locked->forceFill(['payment_status' => Bid::STATUS_FAILED, 'notes' => mb_substr($reason, 0, 500)])->save();
            }
        });
    }

    public function applyDebit(Listing $listing, int $amountCents, string $providerReference, string $reason): Bid
    {
        return DB::transaction(function () use ($listing, $amountCents, $providerReference, $reason): Bid {
            $locked = Listing::query()->lockForUpdate()->findOrFail($listing->id);
            if ($amountCents <= 0 || $amountCents > $locked->total_bid_cents) {
                throw new RuntimeException('The reversal amount is outside the listing balance.');
            }
            $beforeRank = $this->rankings->globalRank($locked);
            $previous = (int) $locked->total_bid_cents;
            $new = $previous - $amountCents;
            $locked->forceFill(['total_bid_cents' => $new, 'last_bid_at' => now()])->save();
            $afterRank = $new > 0 ? $this->rankings->globalRank($locked->fresh()) : null;

            return Bid::query()->create([
                'listing_id' => $locked->id,
                'public_reference' => (string) Str::uuid(),
                'direction' => 'debit',
                'amount_cents' => $amountCents,
                'signed_delta_cents' => -$amountCents,
                'previous_total_cents' => $previous,
                'new_total_cents' => $new,
                'currency' => config('marketplace.currency'),
                'payment_provider' => 'admin_refund',
                'payment_reference' => $providerReference,
                'payment_status' => Bid::STATUS_REFUNDED,
                'ranking_before' => $beforeRank,
                'ranking_after' => $afterRank,
                'notes' => $reason,
                'confirmed_at' => now(),
            ]);
        }, 3);
    }

    public function applyAdminAdjustment(Listing $listing, int $signedAmountCents, string $reason): Bid
    {
        return DB::transaction(function () use ($listing, $signedAmountCents, $reason): Bid {
            $locked = Listing::query()->lockForUpdate()->findOrFail($listing->id);
            $previous = (int) $locked->total_bid_cents;
            $new = $previous + $signedAmountCents;
            if ($signedAmountCents === 0 || $new < 0) {
                throw new RuntimeException('The adjustment must change the balance and cannot make it negative.');
            }

            $beforeRank = $this->rankings->globalRank($locked);
            $reachedAt = now();
            $listingStatus = $new > 0 && $locked->status === Listing::STATUS_PENDING
                ? Listing::STATUS_ACTIVE
                : $locked->status;
            $locked->forceFill([
                'total_bid_cents' => $new,
                'last_bid_at' => $reachedAt,
                'status' => $listingStatus,
            ])->save();
            $afterRank = $new > 0 && $listingStatus === Listing::STATUS_ACTIVE
                ? $this->rankings->globalRank($locked->fresh())
                : null;

            return Bid::query()->create([
                'listing_id' => $locked->id,
                'public_reference' => (string) Str::uuid(),
                'direction' => $signedAmountCents > 0 ? 'credit' : 'debit',
                'amount_cents' => abs($signedAmountCents),
                'signed_delta_cents' => $signedAmountCents,
                'previous_total_cents' => $previous,
                'new_total_cents' => $new,
                'currency' => config('marketplace.currency'),
                'payment_provider' => 'admin_adjustment',
                'payment_reference' => 'admin-'.Str::uuid(),
                'payment_status' => Bid::STATUS_CONFIRMED,
                'ranking_before' => $beforeRank,
                'ranking_after' => $afterRank,
                'notes' => $reason,
                'confirmed_at' => $reachedAt,
            ]);
        }, 3);
    }

    private function activityMessage(string $name, int $amount, ?int $before, ?int $after): string
    {
        $money = '$'.number_format($amount / 100, 2);
        if ($after && $before && $after < $before) {
            return "{$name} added {$money} and moved from #{$before} to #{$after}.";
        }
        if ($after === 1) {
            return "{$name} added {$money} and claimed #1.";
        }

        return "{$name} added {$money} to its cumulative bid.";
    }
}
