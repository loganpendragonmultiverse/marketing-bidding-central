@extends('layouts.app')
@section('title', 'Listing Saved | Marketing Bidding Central')
@section('robots', 'noindex, nofollow')
@section('content')
<section class="result-page narrow"><p class="kicker">Listing saved</p><h1>{{ session('marketplace_submitted_name', 'Your project') }} is waiting for payment.</h1><p>There is no approval queue. It will appear on the board as soon as a confirmed payment is attached to it.</p>
@if(session('marketplace_manage_url'))<div class="token-box"><strong>Save your private edit link.</strong><p>Use it to update the entry or return to payment without creating an account. If it is lost, ownership support will use the contact email you supplied.</p><a href="{{ session('marketplace_manage_url') }}">Open private listing controls</a></div>@endif
<a class="button secondary" href="{{ route('home') }}">Return to rankings</a></section>
@endsection
