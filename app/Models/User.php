<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

/**
 * Access level hierarchy (lower number = more privileged):
 *   0 = Admin, 1 = Semi-Admin, 2 = Moderator, 3 = User
 * A user may only manage (create/edit/delete) accounts with a STRICTLY
 * higher (less privileged) access level than their own -- see canManage().
 */
class User extends Model implements AuthenticatableContract
{
    use Authenticatable, HasFactory, Notifiable;

    protected $primaryKey = 'user_id';

    public $timestamps = false;

    protected $fillable = [
        'first_name', 'last_name', 'username', 'password', 'email',
        'contact_number', 'profile_photo_path', 'role_id', 'access_level',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'access_level' => 'integer',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    public function securityAnswers()
    {
        return $this->hasMany(UserSecurityAnswer::class, 'user_id', 'user_id');
    }

    public function roleName(): string
    {
        return $this->role?->role_name ?: ($this->access_level === 0 ? 'Admin' : 'User');
    }

    public function profilePhotoUrl(): ?string
    {
        return $this->profile_photo_path
            ? asset('storage/'.$this->profile_photo_path)
            : null;
    }

    public function isAdmin(): bool
    {
        return $this->roleName() === 'Admin' || $this->access_level <= 2;
    }

    public function canManage(int $targetAccessLevel): bool
    {
        return $this->access_level < $targetAccessLevel;
    }

    public function canEditEquipment(): bool
    {
        return $this->roleName() === 'Admin' || $this->access_level <= 2;
    }

    public function canDeleteEquipment(): bool
    {
        return $this->roleName() === 'Admin' || $this->access_level <= 1;
    }

    public static function auditRoleLabel(int $accessLevel): string
    {
        return match ($accessLevel) {
            0 => 'Admin',
            1 => 'Semi-Admin',
            2 => 'Moderator',
            default => 'User',
        };
    }
}
