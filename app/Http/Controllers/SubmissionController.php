<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Listing;
use App\Services\ListingService;
use App\Services\PaymentService;
use App\Services\SafeMetadataService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class SubmissionController extends Controller
{
    public function create(PaymentService $payments, SettingsService $settings): View
    {
        return view('marketplace.submit', [
            'categories' => Category::query()->where('active', true)->orderBy('sort_order')->get(),
            'marketOpen' => $payments->available(),
            'minimumBid' => $settings->minimumBidCents(),
            'platforms' => SafeMetadataService::handlePlatformOptions(),
        ]);
    }

    public function store(Request $request, ListingService $listings, PaymentService $payments, SettingsService $settings): RedirectResponse
    {
        $minimum = $settings->minimumBidCents() / 100;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'string', 'max:500'],
            'platform' => ['nullable', Rule::in(array_keys(SafeMetadataService::HANDLE_PLATFORMS))],
            'category_id' => ['required', 'exists:categories,id'],
            'short_description' => ['required', 'string', 'max:240'],
            'description' => ['nullable', 'string', 'max:4000'],
            'contact_email' => ['required', 'email:rfc', 'max:254'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'agree' => ['accepted'],
            'amount' => ['required', 'numeric', 'min:'.$minimum, 'max:1000000'],
            'confirm_payment' => ['accepted'],
            'website' => ['nullable', 'max:0'],
        ]);

        try {
            [$listing, $token] = $listings->create($data, $request->file('logo'));
        } catch (Throwable $error) {
            return back()->withInput()->withErrors(['url' => $error->getMessage()])->withFragment('listing-form');
        }

        $manageUrl = route('submissions.manage', ['listing' => $listing, 'token' => $token]);
        $request->session()->put([
            'marketplace_submitted_name' => $listing->name,
            'marketplace_manage_url' => $manageUrl,
            'marketplace_listing_id' => $listing->id,
        ]);

        if ($payments->available()) {
            try {
                $checkoutUrl = $payments->startCheckout(
                    $listing,
                    (int) round(((float) $data['amount']) * 100),
                    route('bids.success').'?session_id={CHECKOUT_SESSION_ID}',
                    route('submissions.received'),
                );

                return redirect()->away($checkoutUrl);
            } catch (Throwable $error) {
                return redirect()->route('submissions.received')->with('error', $error->getMessage());
            }
        }

        return redirect()->route('submissions.received');
    }

    public function received(): View
    {
        return view('marketplace.received');
    }

    public function manage(Listing $listing, Request $request, ListingService $service, PaymentService $payments, SettingsService $settings): View
    {
        $token = (string) $request->query('token');
        abort_unless(($request->user() && $request->user()->id === $listing->owner_user_id) || $service->authorizeOwner($listing, $token), 403);

        return view('marketplace.manage', [
            'listing' => $listing,
            'token' => $token,
            'categories' => Category::query()->where('active', true)->orderBy('sort_order')->get(),
            'marketOpen' => $payments->available(),
            'minimumBid' => $settings->minimumBidCents(),
            'minimumTopUp' => $settings->minimumTopUpCents(),
            'platforms' => SafeMetadataService::handlePlatformOptions(),
        ]);
    }

    public function update(Listing $listing, Request $request, ListingService $service): RedirectResponse
    {
        $token = (string) $request->input('token');
        abort_unless(($request->user() && $request->user()->id === $listing->owner_user_id) || $service->authorizeOwner($listing, $token), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'string', 'max:500'],
            'platform' => ['nullable', Rule::in(array_keys(SafeMetadataService::HANDLE_PLATFORMS))],
            'category_id' => ['required', 'exists:categories,id'],
            'short_description' => ['required', 'string', 'max:240'],
            'description' => ['nullable', 'string', 'max:4000'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);
        try {
            $service->update($listing, $data, $request->file('logo'));
        } catch (Throwable $error) {
            return back()->withInput()->withErrors(['url' => $error->getMessage()])->withFragment('listing-form');
        }

        return redirect()->route('submissions.manage', array_filter(['listing' => $listing, 'token' => $token]))->with('success', 'Listing updated.');
    }
}
