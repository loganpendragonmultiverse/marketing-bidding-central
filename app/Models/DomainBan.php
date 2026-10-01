<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DomainBan extends Model
{
    protected $fillable = ['normalized_domain', 'reason', 'created_by'];
}
