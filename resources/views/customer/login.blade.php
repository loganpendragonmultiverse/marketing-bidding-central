@extends('layouts.app')
@section('title', 'Customer Login | Marketing Bidding Central')
@section('robots', 'noindex, nofollow')
@section('content')
<section class="page-hero"><p class="kicker">Customer access</p><h1>Manage your listing.</h1><p>Enter the email used at checkout. We will send a six-digit sign-in code that expires after ten minutes.</p></section>
<section class="section-shell compact auth-shell">
<div class="legal-card code-access"><h2>Email me a code</h2><p>No password is needed.</p><form method="post" action="{{ route('customer.send-code') }}">@csrf<label>Email<input name="email" type="email" value="{{ session('code_email', old('email')) }}" autocomplete="email" required></label><button class="button primary" type="submit">Send code</button></form>
@if(session('code_email'))<form method="post" action="{{ route('customer.verify-code') }}">@csrf<input type="hidden" name="email" value="{{ session('code_email') }}"><label>Six-digit code<input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required></label><button class="button primary" type="submit">Verify and sign in</button></form>@endif</div>
@if($errors->any())<div class="notice error">{{ $errors->first() }}</div>@endif</section>
@endsection
