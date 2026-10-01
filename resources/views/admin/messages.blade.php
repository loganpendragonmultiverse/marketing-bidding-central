@extends('layouts.app')
@section('title', 'Contact messages | Marketing Bidding Central')
@section('robots', 'noindex, nofollow')
@section('content')
@include('admin._header', ['eyebrow' => 'Administration', 'title' => 'Contact messages', 'description' => 'Private messages submitted to this installation.'])
<section class="section-shell">
@forelse($messages as $message)
<article class="legal-card">
<h2>{{ $message->subject }}</h2>
<p>{{ $message->name }} · {{ $message->email }} · {{ $message->created_at->format('Y-m-d H:i') }}</p>
<p style="white-space:pre-wrap">{{ $message->message }}</p>
@if($message->resolved_at)<p>Resolved</p>@else
<form method="post" action="{{ route('admin.messages.resolve', $message) }}">@csrf<button class="button secondary">Mark resolved</button></form>
@endif
</article>
@empty<p>No contact messages.</p>@endforelse
{{ $messages->links() }}
</section>
@endsection
