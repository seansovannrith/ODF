<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class UserAlert extends Model
{
    use Auditable;

    protected $fillable = ['alert_text', 'alert_link', 'created_by'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_alert_user')->withPivot('read_at');
    }

    /** Send an alert to one or many users. */
    public static function send(string $text, ?string $link, array $userIds): self
    {
        $alert = static::create([
            'alert_text' => $text,
            'alert_link' => $link,
            'created_by' => auth()->id(),
        ]);
        $alert->users()->attach(array_unique($userIds));

        return $alert;
    }
}
