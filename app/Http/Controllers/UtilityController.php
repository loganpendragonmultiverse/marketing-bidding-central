<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingReport;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class UtilityController extends Controller
{
    public function go(Listing $listing, Request $request, AnalyticsService $analytics): RedirectResponse
    {
        abort_unless($listing->status === Listing::STATUS_ACTIVE, 404);

        if ($listing->metadata_status === 'review_sample') {
            return redirect(route('home', ['listing' => $listing->slug]).'#listing-view');
        }

        $analytics->recordClick($listing, $request);

        return redirect()->away($listing->normalized_url);
    }

    public function view(Listing $listing, Request $request, AnalyticsService $analytics): JsonResponse
    {
        abort_unless($listing->status === Listing::STATUS_ACTIVE, 404);
        $recorded = $analytics->recordImpressions([$listing], $request);

        return response()->json(['recorded' => $recorded]);
    }

    public function report(Listing $listing, Request $request): RedirectResponse
    {
        abort_unless($listing->status === Listing::STATUS_ACTIVE, 404);
        $data = $request->validate([
            'reason' => ['required', 'in:broken-link,misleading,unsafe,spam,other'],
            'details' => ['nullable', 'string', 'max:2000'],
            'email' => ['nullable', 'email:rfc', 'max:254'],
        ]);
        ListingReport::query()->create(['listing_id' => $listing->id, 'reason' => $data['reason'], 'details' => $data['details'] ?? null, 'reporter_email' => $data['email'] ?? null]);

        return back()->with('success', 'Report received. Thank you for helping keep the marketplace useful.');
    }

    public function legal(string $page): View
    {
        abort_unless(in_array($page, ['terms', 'privacy', 'refunds', 'listing-policy'], true), 404);

        return view('marketplace.legal', compact('page'));
    }

    public function sitemap(): Response
    {
        $urls = collect([route('home')])
            ->merge(Category::query()->where('active', true)->get()->map(fn ($c) => route('categories.show', $c)))
            ->merge(Listing::query()->where('status', Listing::STATUS_ACTIVE)->where(fn ($query) => $query->whereNull('metadata_status')->orWhere('metadata_status', '!=', 'review_sample'))->get()->map(fn ($l) => route('listings.show', $l)));

        return response()->view('marketplace.sitemap', compact('urls'))->header('Content-Type', 'application/xml');
    }
}
