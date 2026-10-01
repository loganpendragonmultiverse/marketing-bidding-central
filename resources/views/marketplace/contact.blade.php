@extends('layouts.app')
@section('title', 'Contact | Marketing Bidding Central')
@section('robots', 'noindex, follow')
@section('content')
<section class="page-hero"><p class="kicker">Contact</p><h1>Contact the operator.</h1><p>Questions about a listing, payment, policy, or account are stored in this installation's private contact inbox.</p></section>
<section class="section-shell compact"><div class="legal-card"><form method="post" action="{{ route('contact.store') }}">@csrf
<label>Name<input name="name" value="{{ old('name') }}" required maxlength="160"></label>
<label>Email<input name="email" type="email" value="{{ old('email') }}" required maxlength="190"></label>
<label>Subject<input name="subject" value="{{ old('subject') }}" required maxlength="160"></label>
<label>Message<textarea name="message" required maxlength="12000" rows="7">{{ old('message') }}</textarea></label>
<input class="hp" name="address" tabindex="-1" autocomplete="off" aria-hidden="true">
@if($errors->any())<div class="notice error">{{ $errors->first() }}</div>@endif
<button class="button primary" type="submit">Send message</button>
</form></div></section>
@endsection
