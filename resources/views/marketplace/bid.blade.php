@extends('layouts.app')
@section('title', 'Bid on '.$listing->name.' | Marketing Bidding Central')
@section('robots', 'noindex, follow')
@section('content')
<section class="section-shell payment-layout"><div class="payment-intro"><a class="text-link" href="{{ route('submissions.manage', array_filter(['listing' => $listing, 'token' => $ownerToken])) }}">← Back to listing controls</a><p class="kicker">Owner payment</p><h1>{{ $listing->status === 'pending' ? 'Pay and publish.' : 'Add to the total.' }}</h1><p>{{ $listing->status === 'pending' ? 'Your first confirmed payment publishes the listing.' : 'Every confirmed payment is added to the existing paid total. The leaderboard recalculates immediately.' }}</p><div class="payment-rule"><strong>{{ $listing->formattedBid() }}</strong><span>current confirmed total</span></div></div>
<form class="bid-card payment-card" method="post" action="{{ route('bids.store', $listing) }}" data-bid-calculator data-current-cents="{{ $listing->total_bid_cents }}" data-competing-totals='@json($competingTotals)'>@csrf
  @if($ownerToken !== '')<input type="hidden" name="token" value="{{ $ownerToken }}">@endif
  <div class="form-head"><span>Choose the amount</span><small>{{ $marketOpen ? 'Secure checkout' : 'Market preview' }}</small></div>
  @if($errors->any())<div class="validation">{{ $errors->first() }}</div>@endif
  <label>Amount to add</label><div class="amount-field"><span>$</span><input name="amount" type="number" min="{{ $minimumBid / 100 }}" max="1000000" step="0.01" value="{{ old('amount', number_format($amountCents / 100, 2, '.', '')) }}" required></div>
  <div class="projection projection-detailed"><div><span>Current total</span><strong>{{ $listing->formattedBid() }}</strong></div><div><span>This payment</span><strong data-payment-total>${{ number_format($amountCents / 100, 2) }}</strong></div><div><span>New total</span><strong data-new-total>${{ number_format(($listing->total_bid_cents + $amountCents) / 100, 2) }}</strong></div><div class="projected"><span>Estimated rank</span><strong data-projected-rank>#{{ $projectedRank }}</strong></div></div>
  <label class="check"><input name="confirm" type="checkbox" value="1" required><span>I understand this buys changeable paid placement and all payments are final except where the law requires otherwise.</span></label>
  <button class="button primary full" @disabled(!$marketOpen)>{{ $marketOpen ? 'Continue to secure checkout' : 'Checkout is not open yet' }}</button>
  <p class="micro">Card information stays with the payment provider. The board updates only after payment confirmation.</p>
</form></section>
@endsection
