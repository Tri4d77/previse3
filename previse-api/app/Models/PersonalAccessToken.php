<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Saját PersonalAccessToken model, amely tartalmazza a
 * current_membership_id és context_organization_id mezőket.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $fillable = [
        'tokenable_type',
        'tokenable_id',
        'name',
        'token',
        'abilities',
        'expires_at',
        'last_used_at',
        'current_membership_id',
        'context_organization_id',
    ];
}
