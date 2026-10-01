@extends('layouts.app')
@section('title', 'List a Project | Marketing Bidding Central')
@section('robots', 'noindex, follow')
@section('content')
<section class="page-hero submit-hero"><p class="kicker">Place your project</p><h1>Add the entry.<br>Choose the bid.</h1><p>No account and no approval queue. We check that the destination is public and safe to load, then your listing goes live when payment is confirmed.</p></section>
<section class="section-shell compact"><form id="listing-form" class="panel-form listing-form" method="post" action="{{ route('submissions.store') }}" enctype="multipart/form-data" data-destination-form>@csrf
  <div class="form-head"><span>Project details</span><small>{{ $marketOpen ? 'Checkout available' : 'Market preview' }}</small></div>
  @if($errors->any())<div class="validation"><strong>Please correct these items:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <div class="field-grid"><label>Project name<input name="name" value="{{ old('name') }}" maxlength="120" required></label><label>Website, profile, or handle<input name="url" value="{{ old('url') }}" type="text" inputmode="url" maxlength="500" placeholder="example.com or @yourhandle" required>@error('url')<small class="field-error">{{ $message }}</small>@enderror</label></div>
  <label>Platform <span class="optional">— only needed for a handle</span><select name="platform"><option value="">Website or full social profile URL</option>@foreach($platforms as $value => $label)<option value="{{ $value }}" @selected(old('platform') === $value)>{{ $label }}</option>@endforeach</select><small>No <strong>https://</strong> is required. For a handle such as <strong>@yourname</strong>, choose its platform here.</small></label>
  <label>Category<select name="category_id" required><option value="">Choose one</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>@endforeach</select></label>
  <label>Short description<textarea name="short_description" maxlength="240" rows="3" required>{{ old('short_description') }}</textarea><small>Keep it direct. This is what people see on the board.</small></label>
  <label>Full description<textarea name="description" maxlength="4000" rows="6">{{ old('description') }}</textarea></label>
  <div class="field-grid"><label>Contact email<input name="contact_email" value="{{ old('contact_email') }}" type="email" required><small>Private. Used only for listing and payment support.</small></label><label>Logo (optional)<input name="logo" type="file" accept="image/png,image/jpeg,image/webp"></label></div>
  <div class="entry-bid"><div><span>Starting bid</span><small>Minimum ${{ number_format($minimumBid / 100, 2) }}</small></div><label><span>$</span><input name="amount" type="number" min="{{ $minimumBid / 100 }}" max="1000000" step="0.01" value="{{ old('amount', number_format($minimumBid / 100, 2, '.', '')) }}" required></label></div>
  <input class="honeypot" name="website" tabindex="-1" autocomplete="off">
  <label class="check"><input name="agree" type="checkbox" value="1" required><span>I control this project or have permission to list it, and I accept the <a href="{{ route('legal', 'listing-policy') }}">listing policy</a>.</span></label>
  <label class="check"><input name="confirm_payment" type="checkbox" value="1" required><span>I understand this is changeable paid placement and the operator’s published payment policy applies.</span></label>
  <button class="button primary full" type="submit" @disabled(!$marketOpen)>{{ $marketOpen ? 'Continue to secure checkout' : 'Checkout is not open yet' }}</button>
  <p class="micro">Payment is handled by the configured checkout provider. Your private edit link is created with the listing—no registration needed.</p>
</form></section>
@endsection
