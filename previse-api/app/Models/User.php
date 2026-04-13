<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'email',
        'password',
        'avatar_path',
        'phone',
        'role_id',
        'is_active',
        'email_verified_at',
        'invitation_token',
        'invitation_sent_at',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'invitation_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'invitation_sent_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // ========== KAPCSOLATOK ==========

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_user');
    }

    // ========== RBAC SEGÉD METÓDUSOK ==========

    /**
     * Ellenőrzi, hogy a felhasználónak van-e egy adott engedélye.
     * Pont-szintaxis: "tickets.create"
     */
    public function hasPermission(string $permission): bool
    {
        // Szuper-admin (platform szervezet admin szerepkör) mindent megtehet
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->role->hasPermissionTo($permission);
    }

    /**
     * Szuper-admin-e (platform szervezet + admin szerepkör).
     */
    public function isSuperAdmin(): bool
    {
        return $this->organization->isPlatform()
            && $this->role->slug === 'admin';
    }

    /**
     * Szervezeti admin-e.
     */
    public function isAdmin(): bool
    {
        return $this->role->slug === 'admin';
    }

    /**
     * A felhasználó monogramja (avatar helyett).
     */
    public function getInitialsAttribute(): string
    {
        $parts = explode(' ', trim($this->name));

        if (count($parts) >= 2) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
        }

        return mb_strtoupper(mb_substr($this->name, 0, 2));
    }

    /**
     * Kétfaktoros hitelesítés be van-e kapcsolva.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return ! is_null($this->two_factor_confirmed_at);
    }

    /**
     * Beállítások létrehozása, ha még nincs (lazy creation).
     */
    public function getOrCreateSettings(): UserSetting
    {
        return $this->settings ?? $this->settings()->create();
    }
}
