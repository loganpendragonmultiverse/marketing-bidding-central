<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListingReport extends Model
{
    protected $fillable = ['listing_id', 'reporter_email', 'reason', 'details', 'status', 'admin_note', 'resolved_at'];

    protected $hidden = ['reporter_email'];

    protected function casts(): array
    {
        return ['reporter_email' => 'encrypted', 'resolved_at' => 'datetime'];
    }
}
