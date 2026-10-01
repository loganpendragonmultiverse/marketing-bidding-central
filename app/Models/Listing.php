<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Listing extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_BANNED = 'banned';

    protected $fillable = [
        'owner_user_id', 'category_id', 'public_id', 'name', 'slug', 'url', 'normalized_url',
        'destination_host', 'short_description', 'description', 'logo_path', 'logo_url',
        'image_url', 'contact_email', 'status', 'is_verified', 'is_featured', 'metadata_status',
        'metadata_json', 'approved_at', 'approved_by',
    ];

    protected $hidden = ['contact_email', 'metadata_json'];

    protected function casts(): array
    {
        return [
            'contact_email' => 'encrypted',
            'metadata_json' => 'array',
            'is_verified' => 'boolean',
            'is_featured' => 'boolean',
            'total_bid_cents' => 'integer',
            'click_count' => 'integer',
            'impression_count' => 'integer',
            'last_bid_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(DailyListingMetric::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ListingReport::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function scopeRankable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)->where('total_bid_cents', '>', 0);
    }

    public function scopeRanked(Builder $query): Builder
    {
        return $query->rankable()
            ->orderByDesc('total_bid_cents')
            ->orderBy('last_bid_at')
            ->orderBy('id');
    }

    public function formattedBid(): string
    {
        return '$'.number_format($this->total_bid_cents / 100, 2);
    }
}
