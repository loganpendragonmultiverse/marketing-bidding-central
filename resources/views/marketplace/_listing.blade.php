@php($homeListingUrl = route('home', ['listing' => $listing->slug]).'#listing-view')
<article class="listing {{ $rank === 1 ? 'first' : '' }}" data-inline-listing
  data-name="{{ $listing->name }}" data-slug="{{ $listing->slug }}" data-category="{{ $listing->category->name }}"
  data-short="{{ $listing->short_description }}" data-description="{{ $listing->description }}"
  data-url="{{ $listing->normalized_url }}" data-host="{{ $listing->destination_host }}"
  data-view-count="{{ $listing->impression_count }}" data-click-count="{{ $listing->click_count }}"
  data-total="{{ $listing->formattedBid() }}" data-rank="#{{ $rank }}"
  data-home-url="{{ $homeListingUrl }}"
  data-go-url="{{ route('listings.go', $listing) }}" data-view-url="{{ route('listings.view', $listing) }}"
  data-report-url="{{ route('listings.report', $listing) }}" data-review-sample="{{ $listing->metadata_status === 'review_sample' ? '1' : '0' }}">
  <span class="rank">{{ str_pad((string) $rank, 2, '0', STR_PAD_LEFT) }}</span>
  <span class="logo">{{ mb_strtoupper(mb_substr($listing->name, 0, 1)) }}</span>
  <div class="project"><a class="listing-select" href="{{ route('listings.show', $listing) }}"><strong>{{ $listing->name }}</strong></a><small>{{ $listing->short_description }}</small>@if($listing->metadata_status === 'review_sample')<span class="listing-url">{{ $listing->normalized_url }}</span><em class="sample-badge">Review sample</em>@else<a class="listing-url" href="{{ route('listings.go', $listing) }}" rel="nofollow sponsored noopener">{{ $listing->normalized_url }}</a>@endif</div>
  <a class="category" href="{{ route('categories.show', $listing->category) }}">{{ $listing->category->name }}</a>
  <span class="views">{{ number_format($listing->impression_count) }}</span>
  <span class="visits">{{ number_format($listing->click_count) }}</span>
  <strong class="bid">{{ $listing->formattedBid() }}</strong>
</article>
