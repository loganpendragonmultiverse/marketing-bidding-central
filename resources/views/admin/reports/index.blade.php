@extends('layouts.app')
@section('title', 'Reports | Marketing Bidding Central')
@section('robots', 'noindex, nofollow')
@section('content')
@include('admin._header', ['eyebrow' => 'Moderation', 'title' => 'Listing reports', 'description' => 'Review complaints and preserve a resolution note.'])
<section class="admin-page"><div class="admin-record-grid">@forelse($reports as $report)<article class="admin-card admin-report {{ $report->status !== 'open' ? 'resolved' : '' }}"><div class="admin-card-head"><div><span class="status-pill">{{ ucfirst($report->status) }}</span><h2><a href="{{ route('admin.listings.show', $report->listing) }}">{{ $report->listing->name }}</a></h2></div><small>{{ $report->created_at->format('M j, Y g:i A') }}</small></div><strong>{{ ucfirst($report->reason) }}</strong><p>{{ $report->details ?: 'No additional details.' }}</p>@if($report->status === 'open')<form class="admin-form" method="post" action="{{ route('admin.reports.resolve', $report) }}">@csrf<label>Resolution note<textarea name="admin_note" required></textarea></label><button class="button secondary">Resolve report</button></form>@else<p><strong>Resolution:</strong> {{ $report->admin_note }}</p>@endif</article>@empty<div class="admin-card"><p>No reports yet.</p></div>@endforelse</div>{{ $reports->links() }}</section>
@endsection
