<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Title extends Model
{
    use Auditable;

    protected $fillable = ['name', 'name_kh', 'abbr', 'abbr_kh', 'description'];
}
