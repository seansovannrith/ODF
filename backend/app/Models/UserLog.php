<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'log_type', 'table_name', 'subject_id', 'data', 'ip', 'user_agent'];

    protected $casts = ['data' => 'array'];

    public function user() { return $this->belongsTo(User::class); }

    public static function record(string $type, ?int $userId = null, ?array $data = null): void
    {
        $request = app()->runningInConsole() ? null : request();
        static::create([
            'user_id' => $userId ?? auth()->id(),
            'log_type' => $type,
            'data' => $data,
            'ip' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 255),
        ]);
    }
}
