<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AllowedDomain extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'domain',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // ========== KAPCSOLATOK ==========

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
