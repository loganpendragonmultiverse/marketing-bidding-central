<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityEvent extends Model
{
    protected $fillable = ['listing_id', 'type', 'public_message', 'metadata', 'visible', 'occurred_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'visible' => 'boolean', 'occurred_at' => 'datetime'];
    }
}
