<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    use Auditable;

    protected $table = 'staff';

    protected $fillable = ['staff_code', 'name', 'name_kh', 'gender', 'title_id', 'position_id', 'department_id', 'user_id', 'phone'];

    public function title() { return $this->belongsTo(Title::class); }
    public function position() { return $this->belongsTo(Position::class); }
    public function department() { return $this->belongsTo(Department::class); }
    public function user() { return $this->belongsTo(User::class); }
}
