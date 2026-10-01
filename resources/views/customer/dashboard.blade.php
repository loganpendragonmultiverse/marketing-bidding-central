@extends('layouts.app')
@section('title', 'Customer Account | Marketing Bidding Central')
@section('robots', 'noindex, nofollow')
@section('content')
<section class="page-hero"><p class="kicker">Customer account</p><h1>Your listings.</h1><p>Signed in as {{ auth()->user()->email }}.</p><form method="post" action="{{ route('customer.logout') }}">@csrf<button class="button" type="submit">Sign out</button></form></section>
<section class="section-shell compact"><div class="legal-card account-listings"><h2>Listings</h2>@forelse($listings as $listing)<div class="dashboard-row"><div><strong>{{ $listing->name }}</strong><p>{{ $listing->category->name }} · {{ ucfirst($listing->status) }} · {{ $listing->formattedBid() }}</p></div><a class="button" href="{{ route('submissions.manage', $listing) }}">Manage</a></div>@empty<p>No listings are connected to this account yet.</p>@endforelse</div></section>
@endsection
