<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public static function roleLabel(string $role = '', ?int $accessLevel = null): string
    {
        if ($accessLevel !== null) {
            return User::auditRoleLabel($accessLevel);
        }

        $normalized = trim($role);
        if ($normalized === '') {
            return 'User';
        }

        return match (strtolower($normalized)) {
            'admin' => 'Admin',
            'semi-admin', 'semi admin' => 'Semi-Admin',
            'moderator' => 'Moderator',
            'user' => 'User',
            default => $normalized,
        };
    }

    public static function log(string $action, string $type = 'system', string $information = 'N/A', ?string $actor = null, ?string $role = null): void
    {
        $user = Auth::user();
        $actor = $actor ?? $user?->username ?? 'Unknown';
        $accessLevel = $user?->access_level;
        $roleLabel = self::roleLabel($role ?? $user?->roleName() ?? 'User', $accessLevel);

        AuditLog::create([
            'actor' => $actor,
            'role' => $roleLabel,
            'action' => $action,
            'type' => $type,
            'information' => $information,
            'datetime' => now(),
        ]);
    }

    public static function system(string $action, string $information = 'N/A'): void
    {
        AuditLog::create([
            'actor' => 'System',
            'role' => 'Automation',
            'action' => $action,
            'type' => 'System',
            'information' => $information,
            'datetime' => now(),
        ]);
    }
}
