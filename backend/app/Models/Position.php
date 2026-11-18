<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    use Auditable;

    protected $fillable = ['name', 'name_kh', 'description', 'active'];

    protected $casts = ['active' => 'boolean'];
}
