<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

/**
 * Felhasználó (személy) - szervezet-független.
 *
 * A user globálisan egyedi az email alapján. A szervezeti kapcsolatok
 * a memberships táblában vannak, a user több szervezet tagja is lehet.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_path',
        'phone',
        'is_active',
        'email_verified_at',
        'pending_email',
        'email_change_token',
        'email_change_sent_at',
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
        'email_change_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_change_sent_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // ========== KAPCSOLATOK ==========

    public function settings(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    /**
     * Összes tagság (beleértve az inaktív/törlött-eket).
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Aktív, elfogadott tagságok (amikbe bejelentkezhet).
     */
    public function activeMemberships(): HasMany
    {
        return $this->hasMany(Membership::class)
            ->where('is_active', true)
            ->whereNotNull('joined_at');
    }

    /**
     * Függőben lévő meghívók (még nem fogadta el).
     */
    public function pendingMemberships(): HasMany
    {
        return $this->hasMany(Membership::class)
            ->whereNotNull('invitation_token')
            ->whereNull('joined_at');
    }

    // ========== BIZTONSÁGI / STÁTUSZ ELLENŐRZÉSEK ==========

    /**
     * Bejelentkezhet-e a user?
     *
     * Igen, ha:
     * - is_active = true
     * - email_verified_at kitöltve
     * - password kitöltve
     * - van legalább 1 aktív tagsága
     */
    public function canLogin(): bool
    {
        return $this->is_active
            && ! is_null($this->email_verified_at)
            && ! is_null($this->password)
            && $this->activeMemberships()->exists();
    }

    /**
     * Szuper-admin-e?
     * (Platform szervezet tagja admin szerepkörrel.)
     */
    public function isSuperAdmin(): bool
    {
        return $this->activeMemberships()
            ->whereHas('organization', fn ($q) => $q->where('type', 'platform'))
            ->whereHas('role', fn ($q) => $q->where('slug', 'admin'))
            ->exists();
    }

    /**
     * Platform membership (ha van).
     */
    public function platformMembership(): ?Membership
    {
        return $this->activeMemberships()
            ->whereHas('organization', fn ($q) => $q->where('type', 'platform'))
            ->first();
    }

    /**
     * A user monogramja (avatar helyett).
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
     * Beállítások létrehozása, ha még nincs.
     * firstOrCreate → nem okoz duplikátum-hibát, és friss rekordot ad vissza.
     */
    public function getOrCreateSettings(): UserSetting
    {
        return $this->settings()->firstOrCreate([]);
    }

    // ========== AKTUÁLIS TAGSÁG (az aktív token alapján) ==========

    /**
     * Az aktuális tokenhez tartozó membership (ha van).
     *
     * Szuper-admin impersonation esetén NULL, az context_organization_id-t
     * a PersonalAccessToken-ról külön kell kezelni.
     */
    public function currentMembership(): ?Membership
    {
        $token = $this->currentAccessToken();

        if (! $token || ! $token->current_membership_id) {
            return null;
        }

        return $this->memberships()
            ->where('id', $token->current_membership_id)
            ->first();
    }

    /**
     * Az aktuális token kontextus-szervezet id-je (szuper-admin impersonation).
     */
    public function currentContextOrganizationId(): ?int
    {
        $token = $this->currentAccessToken();

        return $token?->context_organization_id;
    }

    /**
     * Az aktuális aktív szervezet (membership vagy impersonation alapján).
     */
    public function currentOrganization(): ?Organization
    {
        if ($contextOrgId = $this->currentContextOrganizationId()) {
            return Organization::find($contextOrgId);
        }

        return $this->currentMembership()?->organization;
    }

    // ========== ENGEDÉLY ELLENŐRZÉS ==========

    /**
     * Van-e az aktuális kontextusban adott engedélye.
     *
     * - Szuper-admin: mindig IGEN
     * - Impersonation: a platform user szuper-admin → IGEN
     * - Egyébként: a current membership role engedélyei dönt
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $membership = $this->currentMembership();

        if (! $membership) {
            return false;
        }

        return $membership->hasPermission($permission);
    }

    // ========== NOTIFICATIONS ==========

    /**
     * A Laravel alapértelmezett jelszó-visszaállítási notification felülírása,
     * hogy saját Blade sablonunkat használjuk (HU/EN, Previse brand).
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
