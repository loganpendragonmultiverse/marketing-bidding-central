@extends('layouts.app')
@section('title', $category->name.' Rankings | Marketing Bidding Central')
@section('description', $category->description.' Compare ranked '.$category->name.' listings by confirmed paid total, listing views, and link clicks.')
@section('content')
<section class="page-hero aligned-hero"><a class="text-link" href="{{ route('home') }}#rankings">← All rankings</a><p class="kicker">Category leaderboard</p><h1>{{ $category->name }}</h1><p>{{ $category->description }}</p></section>
<section class="section-shell compact">
  <nav class="category-tabs category-page-tabs" aria-label="Ranking categories">
    <a href="{{ route('home') }}#rankings">Overall</a>
    @foreach($categories as $availableCategory)
      <a @class(['active' => $availableCategory->is($category)]) href="{{ route('categories.show', $availableCategory) }}" @if($availableCategory->is($category)) aria-current="page" @endif>{{ $availableCategory->name }} <small>{{ $availableCategory->listings_count }}</small></a>
    @endforeach
    <a class="request-tab" href="{{ route('category-requests.create') }}">+ Request a category</a>
  </nav>
  <div class="leaderboard"><div class="leaderboard-head"><span>Rank / Project</span><span>Category</span><span>Views</span><span>Clicks</span><span>Total bid</span></div>@forelse($listings as $listing) @include('marketplace._listing', ['rank' => $listings->firstItem() + $loop->index]) @empty <div class="empty">No ranked listings yet.</div> @endforelse</div>{{ $listings->links() }}
</section>
@endsection
