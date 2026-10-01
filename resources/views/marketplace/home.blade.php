@extends('layouts.app')
@section('title', 'Marketing Bidding Central | Competitive Project Discovery')
@section('canonical', rtrim(config('app.url'), '/').'/')
@section('content')
<section class="hero">
  <div class="hero-copy">
    <p class="kicker">The paid project leaderboard</p>
    <h1>Put your project<br><em>where people look.</em></h1>
    <p class="hero-lede">Choose your bid and take your place. Higher paid totals move higher on the board.</p>
    <div class="hero-actions"><a class="button primary" href="#rankings">See the board</a><a class="button secondary" href="{{ route('submissions.create') }}">Place your project</a></div>
    <p class="concept-note">One payment. No account. Your listing goes live when the payment is confirmed.</p>
  </div>
  <aside class="market-pulse">
    <div class="pulse-head"><span>Just added</span></div>
    <div class="recent-listings">
      @forelse($recentListings as $listing)
      <a class="recent-listing" href="{{ route('home', ['listing' => $listing->slug]) }}#listing-view">
        <span class="recent-number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
        <span><small>{{ $listing->category->name }}</small><strong>{{ $listing->name }}</strong><em>{{ $listing->short_description }}</em></span>
        <b>{{ $listing->formattedBid() }}</b>
      </a>
      @empty
      @for($spot = 1; $spot <= 3; $spot++)
      <div class="recent-listing recent-placeholder"><span class="recent-number">{{ str_pad((string) $spot, 2, '0', STR_PAD_LEFT) }}</span><span><small>Open spot</small><strong>Waiting for an entry</strong></span><b>&mdash;</b></div>
      @endfor
      @endforelse
    </div>
    <p class="recent-note">The three newest paid entries stay here temporarily.</p>
    <div class="pulse-meta"><span>Entry from <strong>${{ number_format($minimumBid / 100, 2) }}</strong></span><span><strong>{{ $categories->count() }}</strong> categories</span></div>
  </aside>
</section>
<section class="ticker"><div><strong>{{ number_format($totalListings) }}</strong><span>Paid projects</span></div><div><strong>{{ $categories->count() }}</strong><span>Open categories</span></div><div><strong>${{ number_format($totalBids / 100) }}</strong><span>Confirmed bid value</span></div><div><strong>100%</strong><span>Rank rules public</span></div></section>
<section class="section-shell" id="rankings">
  <div class="section-intro"><div><p class="kicker">Live rankings</p><h2>Paid totals decide the order.</h2></div><p>Only confirmed payments count. If two projects have the same total, the one that got there first stays ahead.</p></div>
  <div class="category-tabs"><a class="active" href="{{ route('home') }}#rankings">Overall</a>@foreach($categories as $category)<a href="{{ route('categories.show', $category) }}">{{ $category->name }} <small>{{ $category->listings_count }}</small></a>@endforeach<a class="request-tab" href="{{ route('category-requests.create') }}">+ Request a category</a></div>
  <div class="leaderboard"><div class="leaderboard-head"><span>Rank / Project</span><span>Category</span><span>Views</span><span>Clicks</span><span>Total bid</span></div>
  @forelse($leaders as $listing) @include('marketplace._listing', ['rank' => $loop->iteration]) @empty <div class="empty"><strong>The board is open.</strong><p>The first confirmed payment takes the first position.</p></div> @endforelse</div>
</section>
<section class="section-shell inline-listing-shell {{ $selectedListing ? '' : 'is-empty' }}" id="listing-view" data-listing-view>
  <div class="inline-listing-empty" data-listing-empty><p class="kicker">Listing details</p><h2>Select a project above.</h2><p>Its description, destination, placement, and live view and click counts will appear here without leaving the board.</p></div>
  <article class="inline-listing-card" data-listing-card @if(!$selectedListing) hidden @endif>
    <div class="inline-listing-main"><p class="kicker" data-detail-category>{{ $selectedListing?->category?->name }}</p><div class="detail-title-line"><h2 data-detail-name>{{ $selectedListing?->name }}</h2><span class="sample-badge" data-detail-sample @if($selectedListing?->metadata_status !== 'review_sample') hidden @endif>Review sample</span></div><p class="hero-lede" data-detail-short>{{ $selectedListing?->short_description }}</p><p data-detail-description>{{ $selectedListing?->description }}</p><a class="destination-text" data-detail-url @if($selectedListing && $selectedListing->metadata_status !== 'review_sample') href="{{ route('listings.go', $selectedListing) }}" @endif rel="nofollow sponsored noopener">{{ $selectedListing?->normalized_url }}</a><div class="hero-actions"><a class="button primary" data-detail-go @if($selectedListing && $selectedListing->metadata_status !== 'review_sample') href="{{ route('listings.go', $selectedListing) }}" @else hidden @endif rel="nofollow sponsored noopener">Visit link ↗</a></div></div>
    <aside class="inline-listing-stats"><div><span>Global rank</span><strong data-detail-rank>{{ $selectedRank ? '#'.$selectedRank : '—' }}</strong></div><div><span>Listing views</span><strong data-detail-views>{{ number_format($selectedListing?->impression_count ?? 0) }}</strong></div><div><span>Link clicks</span><strong data-detail-clicks>{{ number_format($selectedListing?->click_count ?? 0) }}</strong></div><div><span>Paid total</span><strong data-detail-total>{{ $selectedListing?->formattedBid() }}</strong></div></aside>
    <details class="inline-report"><summary>Report this listing</summary><form class="stack-form" data-detail-report method="post" action="{{ $selectedListing ? route('listings.report', $selectedListing) : '#' }}">@csrf<select name="reason" required><option value="">Choose a reason</option><option value="broken-link">Broken link</option><option value="misleading">Misleading</option><option value="unsafe">Unsafe</option><option value="spam">Spam</option><option value="other">Other</option></select><textarea name="details" maxlength="2000" placeholder="What should we review?"></textarea><input name="email" type="email" placeholder="Email (optional)"><button class="button secondary">Send report</button></form></details>
  </article>
</section>
<section class="section-shell how" id="how">
  <div class="section-intro"><div><p class="kicker">How it works</p><h2>List it. Pay once. Move up.</h2></div><p>No registration and no approval queue. We check the destination automatically, then publish after confirmed payment.</p></div>
  <div class="process-panel">
    <article><span>LIST</span><div><strong>Add the essentials</strong><p>Enter the project, destination, description, and the amount you want to put behind it.</p></div></article>
    <article><span>PAY</span><div><strong>Complete checkout</strong><p>Your card stays with the payment provider. The listing appears when the charge is confirmed.</p></div></article>
    <article><span>MOVE</span><div><strong>Come back whenever you want</strong><p>Use your private edit link to update the entry, or add another bid to move it higher.</p></div></article>
  </div>
</section>
@endsection
