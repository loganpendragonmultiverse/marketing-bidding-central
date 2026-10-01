<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryRequest extends Model
{
    protected $fillable = [
        'requested_name', 'reason', 'example_url', 'requester_email', 'status', 'admin_note', 'resolved_at',
    ];

    protected $hidden = ['requester_email'];

    protected function casts(): array
    {
        return [
            'requester_email' => 'encrypted',
            'resolved_at' => 'datetime',
        ];
    }
}
