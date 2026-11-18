<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use Auditable;

    protected $fillable = ['title'];

    public function roles() { return $this->belongsToMany(Role::class); }
}
