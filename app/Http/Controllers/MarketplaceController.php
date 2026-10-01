<?php

namespace App\Http\Controllers;

use App\Models\ActivityEvent;
use App\Models\Category;
use App\Models\Listing;
use App\Services\AnalyticsService;
use App\Services\PaymentService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketplaceController extends Controller
{
    public function home(Request $request, AnalyticsService $analytics, PaymentService $payments, SettingsService $settings): View
    {
        $leaders = Listing::query()->ranked()->with('category')->limit(25)->get();
        $selectedListing = null;
        $selectedRank = null;
        $selectedSlug = trim((string) $request->query('listing'));
        if ($selectedSlug !== '') {
            $selectedListing = Listing::query()->where('slug', $selectedSlug)->where('status', Listing::STATUS_ACTIVE)->with('category')->first();
            if ($selectedListing) {
                $analytics->recordImpressions([$selectedListing], $request);
                $rankIndex = Listing::query()->ranked()->pluck('id')->search($selectedListing->id);
                $selectedRank = $rankIndex === false ? null : $rankIndex + 1;
            }
        }

        return view('marketplace.home', [
            'leaders' => $leaders,
            'categories' => Category::query()->where('active', true)->withCount(['listings' => fn ($q) => $q->rankable()])->orderBy('sort_order')->get(),
            'activity' => ActivityEvent::query()->where('visible', true)->latest('occurred_at')->limit(8)->get(),
            'recentListings' => Listing::query()->where('status', Listing::STATUS_ACTIVE)->with('category')->latest('created_at')->latest('id')->limit(3)->get(),
            'totalListings' => Listing::query()->where('status', Listing::STATUS_ACTIVE)->count(),
            'totalBids' => Listing::query()->sum('total_bid_cents'),
            'marketOpen' => $payments->available(),
            'minimumBid' => $settings->minimumBidCents(),
            'selectedListing' => $selectedListing,
            'selectedRank' => $selectedRank,
        ]);
    }

    public function category(Category $category, Request $request, AnalyticsService $analytics): View
    {
        abort_unless($category->active, 404);
        $listings = $category->listings()->ranked()->paginate(30);
        $categories = Category::query()
            ->where('active', true)
            ->withCount(['listings' => fn ($query) => $query->rankable()])
            ->orderBy('sort_order')
            ->get();

        return view('marketplace.category', compact('category', 'categories', 'listings'));
    }

    public function listing(Listing $listing, Request $request, AnalyticsService $analytics, PaymentService $payments, SettingsService $settings): View
    {
        abort_unless($listing->status === Listing::STATUS_ACTIVE, 404);
        $analytics->recordImpressions([$listing], $request);
        $globalRank = Listing::query()->ranked()->pluck('id')->search($listing->id);

        return view('marketplace.listing', [
            'listing' => $listing->load('category'),
            'globalRank' => $globalRank === false ? null : $globalRank + 1,
            'recentBids' => $listing->bids()->where('payment_status', 'confirmed')->latest('confirmed_at')->limit(10)->get(),
            'marketOpen' => $payments->available(),
            'minimumBid' => $settings->minimumBidCents(),
            'canManage' => $request->user() && $request->user()->id === $listing->owner_user_id,
        ]);
    }
}
