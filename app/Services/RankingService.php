<?php

namespace App\Services;

use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class RankingService
{
    public function globalRank(Listing $listing): ?int
    {
        return $this->rank($listing, null);
    }

    public function categoryRank(Listing $listing): ?int
    {
        return $this->rank($listing, $listing->category_id);
    }

    public function projectedGlobalRank(Listing $listing, int $additionalCents): int
    {
        return $this->projectedRank($listing, $additionalCents, null);
    }

    public function projectedCategoryRank(Listing $listing, int $additionalCents): int
    {
        return $this->projectedRank($listing, $additionalCents, $listing->category_id);
    }

    public function ranked(?int $categoryId = null, int $limit = 50): Collection
    {
        return Listing::query()->with('category')
            ->when($categoryId, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->ranked()->limit($limit)->get();
    }

    public function competingTotals(Listing $listing): array
    {
        return Listing::query()->rankable()
            ->whereKeyNot($listing->getKey())
            ->pluck('total_bid_cents')
            ->map(fn ($total): int => (int) $total)
            ->values()
            ->all();
    }

    private function rank(Listing $listing, ?int $categoryId): ?int
    {
        if ($listing->status !== Listing::STATUS_ACTIVE || $listing->total_bid_cents <= 0 || ! $listing->last_bid_at) {
            return null;
        }

        return 1 + Listing::query()->rankable()
            ->whereKeyNot($listing->getKey())
            ->when($categoryId, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->where(function (Builder $query) use ($listing): void {
                $query->where('total_bid_cents', '>', $listing->total_bid_cents)
                    ->orWhere(function (Builder $tie) use ($listing): void {
                        $tie->where('total_bid_cents', $listing->total_bid_cents)
                            ->where(function (Builder $reachedEarlier) use ($listing): void {
                                $reachedEarlier->where('last_bid_at', '<', $listing->last_bid_at)
                                    ->orWhere(function (Builder $sameMoment) use ($listing): void {
                                        $sameMoment->where('last_bid_at', $listing->last_bid_at)
                                            ->where('id', '<', $listing->id);
                                    });
                            });
                    });
            })->count();
    }

    private function projectedRank(Listing $listing, int $additionalCents, ?int $categoryId): int
    {
        $projected = $listing->total_bid_cents + max(0, $additionalCents);

        // A newly completed bid reaches its total after every listing already at that total.
        return 1 + Listing::query()->rankable()
            ->whereKeyNot($listing->getKey())
            ->when($categoryId, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->where('total_bid_cents', '>=', $projected)
            ->count();
    }
}
