<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'Marketing Bidding Central')</title>
  <meta name="description" content="@yield('description', 'Transparent, cumulative bidding for public marketing placement and project discovery.')">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="robots" content="@yield('robots', 'index, follow')"><meta name="theme-color" content="#07110f">
  <link rel="canonical" href="@yield('canonical', url()->current())"><link rel="stylesheet" href="{{ asset('css/app.css') }}?v=7">
  @stack('head')
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
  <a class="brand" href="{{ route('home') }}"><span class="brand-mark"><i></i><i></i><i></i></span><span><strong>Marketing Bidding</strong><small>Central</small></span></a>
  <nav aria-label="Primary"><a href="{{ route('home') }}#rankings">Rankings</a><a href="{{ route('home') }}#how">How it works</a><a href="{{ route('submissions.create') }}">List a project</a><a href="{{ route('customer.login') }}">Customer login</a></nav>
  <a class="header-cta" href="{{ route('submissions.create') }}">Submit a listing</a>
</header>
@if(session('success'))<div class="notice success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="notice error">{{ session('error') }}</div>@endif
@if($errors->any())<div class="notice error"><strong>Please correct the highlighted information.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<main id="main">@yield('content')</main>
<footer>
  <a class="brand" href="{{ route('home') }}"><span class="brand-mark"><i></i><i></i><i></i></span><span><strong>Marketing Bidding Central</strong><small>Self-hosted marketplace · v{{ config('marketplace.version') }}</small></span></a>
  <p>Paid rank reflects confirmed cumulative bid value, not endorsement. Placement can change whenever another project bids.</p>
  <nav class="footer-links" aria-label="Footer"><a href="{{ route('contact') }}">Contact</a><a href="{{ route('category-requests.create') }}">Request a category</a><a href="{{ route('customer.login') }}">Customer login</a><a href="{{ route('legal', 'terms') }}">Terms</a><a href="{{ route('legal', 'privacy') }}">Privacy</a><a href="{{ route('legal', 'refunds') }}">Payment policy</a><a href="{{ route('legal', 'listing-policy') }}">Listing policy</a></nav>
</footer>
<script defer src="{{ asset('js/marketplace.js') }}?v=2"></script>
</body></html>
