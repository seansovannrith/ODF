<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    use Auditable;

    protected $fillable = ['value', 'name', 'name_kh', 'description'];
}
