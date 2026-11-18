<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class UserInfo extends Model
{
    use Auditable;

    protected $fillable = ['user_id', 'gender', 'date_of_birth', 'place_of_birth', 'id_card_no', 'phone', 'address'];

    protected $casts = ['date_of_birth' => 'date:Y-m-d', 'user_id' => 'integer'];

    public function user() { return $this->belongsTo(User::class); }
}
