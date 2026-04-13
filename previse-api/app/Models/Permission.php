<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = [
        'module',
        'action',
        'description',
    ];

    // ========== KAPCSOLATOK ==========

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }

    // ========== SEGÉD METÓDUSOK ==========

    /**
     * Pont-szintaxis: "tickets.create"
     */
    public function getKeyAttribute(): string
    {
        return $this->module . '.' . $this->action;
    }
}
