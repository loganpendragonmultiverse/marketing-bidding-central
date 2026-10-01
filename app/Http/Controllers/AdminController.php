<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\Category;
use App\Models\CategoryRequest;
use App\Models\Listing;
use App\Models\ListingReport;
use App\Models\PaymentTransaction;
use App\Models\WebhookEvent;
use App\Payments\PaymentGatewayInterface;
use App\Services\AuditService;
use App\Services\BidService;
use App\Services\SafeMetadataService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class AdminController extends Controller
{
    public function login(): View
    {
        return view('admin.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $email = (string) config('marketplace.admin_email');
        $hash = (string) config('marketplace.admin_password_hash');
        if ($email === '' || $hash === '' || ! hash_equals(strtolower($email), strtolower($data['email'])) || ! password_verify($data['password'], $hash)) {
            return back()->withInput($request->only('email'))->with('error', 'Invalid administrator credentials.');
        }
        $request->session()->regenerate();
        $request->session()->put('marketplace_admin', $email);

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('marketplace_admin');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function dashboard(SettingsService $settings): View
    {
        return view('admin.dashboard', [
            'active' => Listing::query()->where('status', 'active')->count(),
            'totalListings' => Listing::query()->count(),
            'pendingListings' => Listing::query()->where('status', 'pending')->count(),
            'openReports' => ListingReport::query()->where('status', 'open')->count(),
            'openCategoryRequests' => CategoryRequest::query()->where('status', 'open')->count(),
            'confirmedRevenue' => PaymentTransaction::query()->where('status', 'confirmed')->sum('amount_cents'),
            'recentListings' => Listing::query()->with('category')->latest()->limit(6)->get(),
            'recentPayments' => PaymentTransaction::query()->with('bid.listing')->latest()->limit(6)->get(),
            'marketOpen' => $settings->marketOpen(),
        ]);
    }

    public function listings(Request $request): View
    {
        $query = Listing::query()->with('category')->withCount(['bids', 'reports']);
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('q')) {
            $search = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim((string) $request->input('q'))).'%';
            $query->where(fn ($builder) => $builder->where('name', 'like', $search)->orWhere('normalized_url', 'like', $search));
        }

        return view('admin.listings.index', [
            'listings' => $query->latest()->paginate(30)->withQueryString(),
            'statusCounts' => Listing::query()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
        ]);
    }

    public function createListing(): View
    {
        return view('admin.listings.create', ['categories' => Category::query()->where('active', true)->orderBy('sort_order')->get()]);
    }

    public function storeListing(Request $request, SafeMetadataService $metadata, BidService $bids, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'string', 'max:500'],
            'category_id' => ['required', 'exists:categories,id'],
            'short_description' => ['required', 'string', 'max:240'],
            'description' => ['nullable', 'string', 'max:4000'],
            'contact_email' => ['required', 'email', 'max:254'],
            'status' => ['required', 'in:pending,active'],
            'starting_total' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        try {
            $destination = $metadata->normalize($data['url']);
            $metadata->assertPublicDestination($destination['url']);
        } catch (Throwable $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }
        if (Listing::query()->where('normalized_url', $destination['url'])->exists()) {
            return back()->withInput()->with('error', 'That destination already has a listing.');
        }

        $baseSlug = Str::slug($data['name']) ?: 'listing';
        $slug = $baseSlug;
        for ($suffix = 2; Listing::query()->where('slug', $slug)->exists(); $suffix++) {
            $slug = $baseSlug.'-'.$suffix;
        }

        $listing = Listing::query()->create([
            'category_id' => $data['category_id'],
            'public_id' => (string) Str::uuid(),
            'name' => $data['name'],
            'slug' => $slug,
            'url' => $destination['url'],
            'normalized_url' => $destination['url'],
            'destination_host' => $destination['host'],
            'short_description' => $data['short_description'],
            'description' => $data['description'] ?? null,
            'contact_email' => $data['contact_email'],
            'status' => $data['status'],
            'metadata_status' => 'admin_created',
            'approved_at' => $data['status'] === Listing::STATUS_ACTIVE ? now() : null,
            'approved_by' => (string) session('marketplace_admin'),
        ]);
        $audit->record('listing.created', $data['reason'], $listing, null, $listing->only(['name', 'normalized_url', 'category_id', 'status']), $request);

        $startingTotal = (int) round(((float) ($data['starting_total'] ?? 0)) * 100);
        if ($startingTotal > 0) {
            $adjustment = $bids->applyAdminAdjustment($listing, $startingTotal, $data['reason']);
            $audit->record('listing.balance_adjusted', $data['reason'], $listing, ['total_bid_cents' => 0], ['total_bid_cents' => $adjustment->new_total_cents], $request);
        }

        return redirect()->route('admin.listings.show', $listing)->with('success', 'Listing created.');
    }

    public function showListing(Listing $listing): View
    {
        return view('admin.listings.show', [
            'listing' => $listing->load(['category', 'metrics' => fn ($query) => $query->latest('metric_date')->limit(30)]),
            'categories' => Category::query()->orderBy('sort_order')->get(),
            'bids' => $listing->bids()->with('transaction')->latest()->paginate(40),
            'reports' => $listing->reports()->latest()->get(),
            'realPaidCents' => (int) PaymentTransaction::query()->whereHas('bid', fn ($query) => $query->where('listing_id', $listing->id))->where('status', 'confirmed')->sum('amount_cents'),
        ]);
    }

    public function adjustListing(Listing $listing, Request $request, BidService $bids, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'direction' => ['required', 'in:add,subtract'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $before = (int) $listing->total_bid_cents;
        $signed = (int) round(((float) $data['amount']) * 100) * ($data['direction'] === 'subtract' ? -1 : 1);
        try {
            $adjustment = $bids->applyAdminAdjustment($listing, $signed, $data['reason']);
            $audit->record('listing.balance_adjusted', $data['reason'], $listing, ['total_bid_cents' => $before], ['total_bid_cents' => $adjustment->new_total_cents], $request);

            return back()->with('success', 'Listing balance adjusted. This is recorded as an admin adjustment, not a Stripe payment.');
        } catch (Throwable $error) {
            return back()->with('error', $error->getMessage());
        }
    }

    public function payments(): View
    {
        return view('admin.payments.index', [
            'transactions' => PaymentTransaction::query()->with('bid.listing')->latest()->paginate(40),
            'bids' => Bid::query()->with(['listing', 'transaction'])->latest()->paginate(50, ['*'], 'ledger_page'),
        ]);
    }

    public function reports(): View
    {
        return view('admin.reports.index', ['reports' => ListingReport::query()->with('listing')->latest()->paginate(50)]);
    }

    public function categoryRequests(): View
    {
        return view('admin.category-requests.index', ['categoryRequests' => CategoryRequest::query()->latest()->paginate(50)]);
    }

    public function categories(): View
    {
        return view('admin.categories.index', ['categories' => Category::query()->withCount('listings')->orderBy('sort_order')->get()]);
    }

    public function system(SettingsService $settings): View
    {
        return view('admin.system.index', [
            'marketOpen' => $settings->marketOpen(),
            'minimumBid' => $settings->minimumBidCents(),
            'webhooks' => WebhookEvent::query()->latest()->limit(30)->get(),
            'audits' => AuditLog::query()->latest('created_at')->limit(50)->get(),
        ]);
    }

    public function enforce(Listing $listing, Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:rejected,suspended,banned'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $before = $listing->only(['status']);
        $listing->forceFill(['status' => $data['status']])->save();
        $audit->record('listing.enforced', $data['reason'], $listing, $before, $listing->only(['status']), $request);

        return back()->with('success', "{$listing->name} is now {$data['status']}.");
    }

    public function updateListing(Listing $listing, Request $request, SafeMetadataService $metadata, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'string', 'max:500'],
            'category_id' => ['required', 'exists:categories,id'],
            'short_description' => ['required', 'string', 'max:240'],
            'description' => ['nullable', 'string', 'max:4000'],
            'status' => ['required', 'in:pending,active,rejected,suspended,banned'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        try {
            $destination = $metadata->normalize($data['url']);
            $metadata->assertPublicDestination($destination['url']);
        } catch (Throwable $error) {
            return back()->with('error', $error->getMessage());
        }

        $duplicate = Listing::query()->where('normalized_url', $destination['url'])->whereKeyNot($listing->id)->exists();
        if ($duplicate) {
            return back()->with('error', 'That destination already has a listing.');
        }

        $before = $listing->only(['name', 'normalized_url', 'category_id', 'short_description', 'description', 'status']);
        $listing->forceFill([
            'name' => $data['name'],
            'url' => $destination['url'],
            'normalized_url' => $destination['url'],
            'destination_host' => $destination['host'],
            'category_id' => $data['category_id'],
            'short_description' => $data['short_description'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
        ])->save();
        $audit->record('listing.updated', $data['reason'], $listing, $before, $listing->only(array_keys($before)), $request);

        return redirect()->route('admin.listings.show', $listing)->with('success', 'Listing updated.');
    }

    public function saveCategory(Request $request, ?Category $category = null): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'description' => ['nullable', 'string', 'max:1000'], 'sort_order' => ['required', 'integer', 'min:0'], 'active' => ['nullable', 'boolean']]);
        Category::query()->updateOrCreate(['id' => $category?->id], [
            'name' => $data['name'], 'slug' => $category?->slug ?: Str::slug($data['name']),
            'description' => $data['description'] ?? null, 'sort_order' => $data['sort_order'], 'active' => $request->boolean('active'),
        ]);

        return back()->with('success', 'Category saved.');
    }

    public function settings(Request $request, SettingsService $settings, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['minimum_bid' => ['required', 'numeric', 'min:1', 'max:1000000'], 'market_open' => ['nullable', 'boolean'], 'reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $before = ['minimum_bid_cents' => $settings->minimumBidCents(), 'market_open' => $settings->get('market_open', false)];
        $settings->set('minimum_bid_cents', (int) round($data['minimum_bid'] * 100), 'integer', true);
        $settings->set('market_open', $request->boolean('market_open') ? '1' : '0', 'boolean', true);
        $audit->record('settings.updated', $data['reason'], null, $before, ['minimum_bid_cents' => (int) round($data['minimum_bid'] * 100), 'market_open' => $request->boolean('market_open')], $request);

        return back()->with('success', 'Marketplace settings saved. The environment launch gate still applies.');
    }

    public function resolveReport(ListingReport $report, Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['admin_note' => ['required', 'string', 'min:5', 'max:2000']]);
        $report->forceFill(['status' => 'resolved', 'admin_note' => $data['admin_note'], 'resolved_at' => now()])->save();
        $audit->record('report.resolved', $data['admin_note'], $report, ['status' => 'open'], ['status' => 'resolved'], $request);

        return back()->with('success', 'Report resolved.');
    }

    public function resolveCategoryRequest(CategoryRequest $categoryRequest, Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:reviewed,accepted,declined'],
            'admin_note' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $before = $categoryRequest->only(['status', 'admin_note']);
        $categoryRequest->forceFill([
            'status' => $data['status'],
            'admin_note' => $data['admin_note'],
            'resolved_at' => now(),
        ])->save();
        $audit->record('category_request.resolved', $data['admin_note'], $categoryRequest, $before, $categoryRequest->only(['status', 'admin_note']), $request);

        return back()->with('success', 'Category request updated.');
    }

    public function refund(PaymentTransaction $transaction, Request $request, PaymentGatewayInterface $gateway, BidService $bids, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.5'], 'reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $amount = (int) round($data['amount'] * 100);
        try {
            DB::transaction(function () use ($transaction, $amount, $data, $gateway, $bids, $audit, $request): void {
                $locked = PaymentTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
                if ($locked->status !== 'confirmed' || $amount > $locked->amount_cents - $locked->refunded_cents) {
                    throw new \RuntimeException('Refund is outside the remaining confirmed payment.');
                }
                $listing = Listing::query()->lockForUpdate()->findOrFail($locked->bid->listing_id);
                if ($amount > $listing->total_bid_cents) {
                    throw new \RuntimeException('Refund exceeds the remaining listing balance.');
                }
                $result = $gateway->refund($locked->load('bid.listing'), $amount, $data['reason']);
                if (! in_array($result->status, ['succeeded', 'pending'], true)) {
                    throw new \RuntimeException('The provider did not accept the refund.');
                }
                $debit = $bids->applyDebit($listing, $amount, $result->id, $data['reason']);
                $total = $locked->refunded_cents + $amount;
                $locked->forceFill(['refunded_cents' => $total, 'status' => $total === $locked->amount_cents ? 'refunded' : 'confirmed'])->save();
                $audit->record('payment.refunded', $data['reason'], $locked, ['status' => 'confirmed'], ['refund_id' => $result->id, 'refund_status' => $result->status, 'refunded_cents' => $total, 'debit_bid' => $debit->id], $request);
            });

            return back()->with('success', 'Refund recorded and ranking balance adjusted.');
        } catch (Throwable $error) {
            return back()->with('error', $error->getMessage());
        }
    }
}
