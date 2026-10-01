@extends('layouts.app')
@section('title', ucfirst(str_replace('-', ' ', $page)).' | Marketing Bidding Central')
@section('description', 'Operator policy for this Marketing Bidding Central installation.')
@section('content')
<section class="page-hero"><p class="kicker">Operator policy</p><h1>{{ ucfirst(str_replace('-', ' ', $page)) }}</h1></section>
<section class="section-shell compact"><article class="legal-card">
@include('legal.'.$page)
<p><a href="{{ route('contact') }}">Contact the operator</a></p>
</article></section>
@endsection
