<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Egy user tagsága egy adott szervezetben, egy adott szerepkörrel.
 *
 * Egy user több szervezetnek is lehet tagja (más-más szerepkörrel).
 * A tevékenységek (bejelentések, feladatok, kommentek) a membership_id-re
 * mutatnak majd, így a szervezetenkénti adat-elkülönítés tiszta.
 */
class Membership extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'organization_id',
        'role_id',
        'is_active',
        'invitation_token',
        'invitation_sent_at',
        'joined_at',
        'last_active_at',
        'leave_token',
        'leave_sent_at',
    ];

    protected $hidden = [
        'invitation_token',
        'leave_token',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'invitation_sent_at' => 'datetime',
            'joined_at' => 'datetime',
            'last_active_at' => 'datetime',
            'leave_sent_at' => 'datetime',
        ];
    }

    // ========== KAPCSOLATOK ==========

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_membership');
    }

    // ========== SEGÉD METÓDUSOK ==========

    /**
     * Ellenőrzi, hogy ez a tagság jogosult egy adott engedélyre.
     */
    public function hasPermission(string $permission): bool
    {
        return $this->role->hasPermissionTo($permission);
    }

    /**
     * Függőben lévő meghívó tagság (még nincs elfogadva).
     */
    public function isPending(): bool
    {
        return ! is_null($this->invitation_token) && is_null($this->joined_at);
    }

    /**
     * Lejárt meghívó (alapértelmezett: config/auth.php invitation_expires_days).
     */
    public function isInvitationExpired(): bool
    {
        if (! $this->invitation_sent_at) {
            return false;
        }

        $expiresInDays = (int) config('auth.invitation_expires_days', 7);

        return $this->invitation_sent_at->diffInDays(now()) > $expiresInDays;
    }

    // ========== STATIKUS HELPER-EK ==========

    /**
     * Aktív tagság megadott user + organization párhoz.
     */
    public static function activeFor(int $userId, int $organizationId): ?self
    {
        return self::where('user_id', $userId)
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->whereNotNull('joined_at')
            ->first();
    }
}
