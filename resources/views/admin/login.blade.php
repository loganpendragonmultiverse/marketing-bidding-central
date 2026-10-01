@extends('layouts.app')
@section('title', 'Marketplace Administration')
@section('robots', 'noindex, nofollow')
@section('content')<section class="result-page narrow"><p class="kicker">Restricted operations</p><h1>Administrator sign in</h1><form class="panel-form" method="post" action="{{ route('admin.authenticate') }}">@csrf<label>Email<input name="email" type="email" value="{{ old('email') }}" required autocomplete="username"></label><label>Password<input name="password" type="password" required autocomplete="current-password"></label><button class="button primary full">Sign in</button></form></section>@endsection
