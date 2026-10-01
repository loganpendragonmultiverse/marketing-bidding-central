<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['admin_identifier', 'action', 'auditable_type', 'auditable_id', 'before', 'after', 'reason', 'ip_hash', 'created_at'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];
    }
}
