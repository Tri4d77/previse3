<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Group extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'description',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Tagok - memberships-en keresztül (nem user-ek közvetlenül).
     */
    public function memberships(): BelongsToMany
    {
        return $this->belongsToMany(Membership::class, 'group_membership');
    }
}
