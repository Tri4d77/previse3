<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'description',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    // ========== KAPCSOLATOK ==========

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    // ========== SEGÉD METÓDUSOK ==========

    /**
     * Ellenőrzi, hogy a szerepkörnek van-e egy adott engedélye.
     */
    public function hasPermission(string $module, string $action): bool
    {
        return $this->permissions()
            ->where('module', $module)
            ->where('action', $action)
            ->exists();
    }

    /**
     * Ellenőrzi, hogy a szerepkörnek van-e egy adott engedélye (pont-szintaxis: "tickets.create").
     */
    public function hasPermissionTo(string $permission): bool
    {
        [$module, $action] = explode('.', $permission, 2);

        return $this->hasPermission($module, $action);
    }
}
