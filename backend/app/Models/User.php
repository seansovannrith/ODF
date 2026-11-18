<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use Auditable, HasApiTokens, Notifiable;

    protected $fillable = [
        'name', 'name_kh', 'username', 'email', 'email_verified_at', 'password',
        'two_factor_secret', 'two_factor_enabled',
        'title_id', 'position_id', 'department_id', 'phone', 'signature', 'photo', 'active',
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'photo'];

    protected $appends = ['signature_url', 'photo_url'];

    /** permission titles, loaded once per request */
    protected ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'active' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'email_verified_at' => 'datetime',
        ];
    }

    public function title() { return $this->belongsTo(Title::class); }
    public function position() { return $this->belongsTo(Position::class); }
    public function department() { return $this->belongsTo(Department::class); }
    public function roles() { return $this->belongsToMany(Role::class); }
    public function staff() { return $this->hasOne(Staff::class); }
    public function info() { return $this->hasOne(UserInfo::class); }

    public function alerts()
    {
        return $this->belongsToMany(UserAlert::class, 'user_alert_user')->withPivot('read_at');
    }

    public function permissionTitles(): array
    {
        if ($this->permissionCache === null) {
            $this->permissionCache = Permission::whereHas('roles.users', fn ($q) => $q->where('users.id', $this->id))
                ->pluck('title')->all();
        }

        return $this->permissionCache;
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissionTitles(), true);
    }

    /** Registers documents and manages flows */
    public function canRegisterDocuments(): bool
    {
        return $this->hasPermission('document_create');
    }

    public function seesAllDocuments(): bool
    {
        return $this->hasPermission('document_grand_access');
    }

    public function getSignatureUrlAttribute(): ?string
    {
        return $this->signature ? '/api/users/'.$this->id.'/signature' : null;
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo
            ? '/api/users/'.$this->id.'/photo?v='.hash('sha256', $this->photo)
            : null;
    }
}
