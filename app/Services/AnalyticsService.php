<?php

namespace App\Services;

use App\Models\DailyListingMetric;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function recordImpressions(iterable $listings, Request $request): bool
    {
        $recorded = false;
        foreach ($listings as $listing) {
            $recorded = $this->record($listing, 'impressions', $request) || $recorded;
        }

        return $recorded;
    }

    public function recordClick(Listing $listing, Request $request): bool
    {
        return $this->record($listing, 'clicks', $request);
    }

    private function record(Listing $listing, string $column, Request $request): bool
    {
        $bot = $this->looksLikeBot((string) $request->userAgent());
        $fingerprint = hash_hmac('sha256', implode('|', [$request->ip(), $request->userAgent(), $listing->id, $column, now()->format('Y-m-d-H-i')]), (string) config('marketplace.analytics_salt'));

        if (! Cache::add('metric:'.$fingerprint, true, now()->addMinutes(2))) {
            return false;
        }

        DB::transaction(function () use ($listing, $column, $bot): void {
            $metric = DailyListingMetric::query()
                ->where('listing_id', $listing->id)
                ->whereDate('metric_date', now()->toDateString())
                ->lockForUpdate()
                ->first();
            if (! $metric) {
                $metric = DailyListingMetric::query()->create([
                    'listing_id' => $listing->id,
                    'metric_date' => now()->toDateString(),
                ]);
            }

            if ($bot) {
                $metric->increment('bot_filtered');

                return;
            }

            $metric->increment($column);
            $listing->increment($column === 'clicks' ? 'click_count' : 'impression_count');
        }, 5);

        return ! $bot;
    }

    private function looksLikeBot(string $userAgent): bool
    {
        return $userAgent === '' || (bool) preg_match('/bot|crawler|spider|preview|curl|wget|headless|monitor|uptime/i', $userAgent);
    }
}
