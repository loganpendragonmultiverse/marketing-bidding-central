@extends('layouts.app')
@section('title', 'Admin overview | Marketing Bidding Central')
@section('robots', 'noindex, nofollow')
@section('content')
@include('admin._header', ['eyebrow' => 'Marketplace administration', 'title' => 'Overview', 'description' => 'The essentials, without every operational record on one screen.'])
<section class="admin-page">
  <div class="admin-stats">
    <a href="{{ route('admin.listings.index') }}"><span>Listings</span><strong>{{ $totalListings }}</strong><small>{{ $active }} active · {{ $pendingListings }} pending</small></a>
    <a href="{{ route('admin.payments.index') }}"><span>Confirmed Stripe revenue</span><strong>${{ number_format($confirmedRevenue / 100, 2) }}</strong><small>Admin adjustments excluded</small></a>
    <a href="{{ route('admin.reports.index') }}"><span>Open reports</span><strong>{{ $openReports }}</strong><small>Review listing complaints</small></a>
    <a href="{{ route('admin.category-requests.index') }}"><span>Category requests</span><strong>{{ $openCategoryRequests }}</strong><small>Awaiting a decision</small></a>
  </div>
  <div class="admin-overview-grid">
    <article class="admin-card"><div class="admin-card-head"><div><p class="kicker">Recently added</p><h2>Listings</h2></div><a class="button primary" href="{{ route('admin.listings.create') }}">Add listing</a></div>
      <div class="admin-list">@foreach($recentListings as $listing)<a href="{{ route('admin.listings.show', $listing) }}"><span><strong>{{ $listing->name }}</strong><small>{{ $listing->category->name }} · {{ ucfirst($listing->status) }}</small></span><b>{{ $listing->formattedBid() }}</b></a>@endforeach</div>
      <a class="admin-more" href="{{ route('admin.listings.index') }}">View all listings →</a>
    </article>
    <article class="admin-card"><div class="admin-card-head"><div><p class="kicker">Recent activity</p><h2>Payments</h2></div><span class="status-pill {{ $marketOpen ? 'good' : 'warn' }}">Market {{ $marketOpen ? 'open' : 'closed' }}</span></div>
      <div class="admin-list">@forelse($recentPayments as $transaction)<a href="{{ route('admin.listings.show', $transaction->bid->listing) }}"><span><strong>{{ $transaction->bid->listing->name }}</strong><small>{{ ucfirst($transaction->status) }} · {{ $transaction->created_at->format('M j, Y g:i A') }}</small></span><b>${{ number_format($transaction->amount_cents / 100, 2) }}</b></a>@empty<p>No payment transactions yet.</p>@endforelse</div>
      <a class="admin-more" href="{{ route('admin.payments.index') }}">Open payment history →</a>
    </article>
  </div>
</section>
@endsection
