<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    use Auditable;

    protected $fillable = ['name', 'value', 'description', 'photo'];

    public static function get(string $name, $default = null)
    {
        return static::where('name', $name)->value('value') ?? $default;
    }
}
