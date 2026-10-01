<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyListingMetric extends Model
{
    protected $fillable = ['listing_id', 'metric_date', 'impressions', 'clicks', 'bot_filtered'];

    protected function casts(): array
    {
        return ['metric_date' => 'date', 'impressions' => 'integer', 'clicks' => 'integer', 'bot_filtered' => 'integer'];
    }
}
