<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';
    public $timestamps = false;
    protected $fillable = ['actor', 'role', 'action', 'type', 'information', 'datetime'];

    protected function casts(): array
    {
        return ['datetime' => 'datetime'];
    }
}
