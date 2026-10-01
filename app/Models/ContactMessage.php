<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $fillable = ['name', 'email', 'subject', 'message'];

    protected function casts(): array
    {
        return ['email' => 'encrypted', 'message' => 'encrypted', 'resolved_at' => 'datetime'];
    }
}
