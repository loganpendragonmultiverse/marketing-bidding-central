<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\PaymentTransaction;
use App\Services\ListingService;
use App\Services\PaymentService;
use App\Services\RankingService;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class BidController extends Controller
{
    public function preview(Listing $listing, Request $request, RankingService $rankings, SettingsService $settings, PaymentService $payments, ListingService $listings): View
    {
        $ownerToken = (string) $request->query('token');
        abort_unless(in_array($listing->status, [Listing::STATUS_PENDING, Listing::STATUS_ACTIVE], true), 404);
        abort_unless($this->canManage($listing, $request, $listings, $ownerToken), 403);
        $minimum = $listing->status === Listing::STATUS_ACTIVE ? $settings->minimumTopUpCents() : $settings->minimumBidCents();
        $amount = max($minimum, (int) round(((float) $request->query('amount', $minimum / 100)) * 100));

        return view('marketplace.bid', [
            'listing' => $listing,
            'amountCents' => $amount,
            'projectedRank' => $rankings->projectedGlobalRank($listing, $amount),
            'minimumBid' => $minimum,
            'marketOpen' => $payments->available(),
            'ownerToken' => $ownerToken,
            'competingTotals' => $rankings->competingTotals($listing),
        ]);
    }

    public function store(Listing $listing, Request $request, PaymentService $payments, SettingsService $settings, ListingService $listings): RedirectResponse
    {
        $ownerToken = (string) $request->input('token');
        abort_unless(in_array($listing->status, [Listing::STATUS_PENDING, Listing::STATUS_ACTIVE], true), 404);
        abort_unless($this->canManage($listing, $request, $listings, $ownerToken), 403);
        $minimum = ($listing->status === Listing::STATUS_ACTIVE ? $settings->minimumTopUpCents() : $settings->minimumBidCents()) / 100;
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:'.$minimum, 'max:1000000'], 'confirm' => ['accepted']]);
        try {
            $url = $payments->startCheckout(
                $listing,
                (int) round(((float) $data['amount']) * 100),
                route('bids.success').'?session_id={CHECKOUT_SESSION_ID}',
                $listing->status === Listing::STATUS_PENDING ? route('submissions.manage', ['listing' => $listing, 'token' => $ownerToken]) : route('listings.show', $listing).'?checkout=cancelled',
            );

            return redirect()->away($url);
        } catch (Throwable $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }
    }

    public function success(Request $request): View
    {
        return view('marketplace.bid-success', [
            'submittedName' => session('marketplace_submitted_name'),
            'manageUrl' => session('marketplace_manage_url'),
            'paymentSession' => mb_substr((string) $request->query('session_id'), 0, 255),
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $sessionId = (string) $request->validate([
            'session_id' => ['required', 'string', 'max:255'],
        ])['session_id'];

        $confirmed = PaymentTransaction::query()
            ->where('provider_transaction_id', $sessionId)
            ->where('status', 'confirmed')
            ->exists();

        return response()->json(['confirmed' => $confirmed]);
    }

    private function canManage(Listing $listing, Request $request, ListingService $listings, string $ownerToken): bool
    {
        return ($request->user() && $request->user()->id === $listing->owner_user_id)
            || $listings->authorizeOwner($listing, $ownerToken);
    }
}
