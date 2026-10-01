@extends('layouts.app')
@section('title', 'Payment Processing | Marketing Bidding Central')
@section('robots', 'noindex, nofollow')
@section('content')
<section class="result-page payment-result" @if($paymentSession) data-analytics-payment-session="{{ $paymentSession }}" data-payment-status-url="{{ route('bids.status') }}" @endif><div class="result-copy"><p class="kicker">Payment received</p><h1>Stripe is confirming it.</h1><p>The paid total and rank update after Stripe confirms the charge. This normally happens quickly—do not pay again if it takes a moment.</p>
@if($manageUrl)<div class="token-box"><strong>Save your private edit link.</strong><p>No account is required. Use this link whenever you need to change the listing or add another bid.</p><a href="{{ $manageUrl }}">Manage {{ $submittedName ?: 'your listing' }}</a></div>@endif
<a class="button primary" href="{{ route('home') }}">View the leaderboard</a></div></section>
@endsection
