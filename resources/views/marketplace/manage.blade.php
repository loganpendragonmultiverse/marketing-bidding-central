@extends('layouts.app')
@section('title', 'Manage '.$listing->name)
@section('robots', 'noindex, nofollow')
@section('content')
<section class="page-hero manage-hero"><p class="kicker">Private listing controls</p><h1>{{ $listing->name }}</h1><p>{{ $listing->status === 'active' ? 'Your entry is live. Changes save immediately.' : 'Your entry is saved and will go live after its first confirmed payment.' }}</p></section>
<section class="section-shell compact manage-grid">
  <form id="listing-form" class="panel-form listing-form" method="post" action="{{ route('submissions.update', $listing) }}" enctype="multipart/form-data" data-destination-form>@csrf<input type="hidden" name="token" value="{{ $token }}">
    <div class="form-head"><span>Edit listing</span><small>{{ ucfirst($listing->status) }}</small></div>
    @if($errors->any())<div class="validation">{{ $errors->first() }}</div>@endif
    <div class="field-grid"><label>Project name<input name="name" value="{{ old('name', $listing->name) }}" maxlength="120" required></label><label>Website, profile, or handle<input name="url" value="{{ old('url', $listing->url) }}" type="text" inputmode="url" maxlength="500" required>@error('url')<small class="field-error">{{ $message }}</small>@enderror</label></div>
    <label>Platform <span class="optional">— only needed for a handle</span><select name="platform"><option value="">Website or full social profile URL</option>@foreach($platforms as $value => $label)<option value="{{ $value }}" @selected(old('platform') === $value)>{{ $label }}</option>@endforeach</select><small>Leave this blank for a website or a full social profile URL.</small></label>
    <label>Category<select name="category_id" required>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id', $listing->category_id) == $category->id)>{{ $category->name }}</option>@endforeach</select></label>
    <label>Short description<textarea name="short_description" maxlength="240" rows="3" required>{{ old('short_description', $listing->short_description) }}</textarea></label>
    <label>Full description<textarea name="description" maxlength="4000" rows="6">{{ old('description', $listing->description) }}</textarea></label>
    <label>Replace logo (optional)<input name="logo" type="file" accept="image/png,image/jpeg,image/webp"></label>
    <button class="button primary" type="submit">Save changes</button>
  </form>
  <aside class="status-card manage-status"><p class="kicker">Placement controls</p><span>Listing status</span><strong>{{ $listing->status === 'pending' ? 'Waiting for payment' : ucfirst($listing->status) }}</strong><span>Confirmed paid total</span><strong>{{ $listing->formattedBid() }}</strong><div class="owner-bid-note"><b>{{ $listing->status === 'active' ? 'Add funds to move higher' : 'Pay the starting bid to publish' }}</b><p>{{ $listing->status === 'active' ? 'New payments add to the existing total. A $3.00 payment on a $5.00 listing creates an $8.00 total.' : 'The listing stays private until Stripe confirms its first payment.' }}</p></div><a class="button primary full" href="{{ route('bids.preview', ['listing' => $listing, 'token' => $token]) }}">{{ $listing->status === 'active' ? 'Add to paid total' : ($marketOpen ? 'Pay and publish' : 'Checkout is not open') }}</a><p class="private-note">Only you can use these payment controls. Keep this management link private.</p></aside>
</section>
@endsection
