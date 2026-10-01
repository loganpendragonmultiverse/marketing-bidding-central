@extends('layouts.app')
@section('title', 'Request a Category | Marketing Bidding Central')
@section('description', 'Suggest a new category for the Marketing Bidding Central project leaderboard.')
@section('content')
<section class="page-hero aligned-hero"><a class="text-link" href="{{ route('home') }}#rankings">← Back to rankings</a><p class="kicker">Category requests</p><h1>What are we missing?</h1><p>If your project does not fit the current board, tell us what category would make sense.</p></section>
<section class="section-shell compact"><form class="panel-form request-category-form" method="post" action="{{ route('category-requests.store') }}">@csrf
  <label>Requested category<input name="requested_name" value="{{ old('requested_name') }}" maxlength="100" required>@error('requested_name')<small class="field-error">{{ $message }}</small>@enderror</label>
  <label>Why should it be added? <span class="optional">— optional</span><textarea name="reason" maxlength="2000">{{ old('reason') }}</textarea></label>
  <label>Example project or URL <span class="optional">— optional</span><input name="example_url" value="{{ old('example_url') }}" maxlength="500" placeholder="example.com/project">@error('example_url')<small class="field-error">{{ $message }}</small>@enderror</label>
  <label>Your email <span class="optional">— optional</span><input name="requester_email" type="email" value="{{ old('requester_email') }}" maxlength="254"></label>
  <input class="honeypot" name="website" tabindex="-1" autocomplete="off">
  <button class="button primary">Send category request</button>
</form></section>
@endsection
