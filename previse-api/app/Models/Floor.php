<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Szint (egy helyszínen belül, opcionális).
 */
class Floor extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'location_id',
        'name',
        'level',
        'description',
        'floor_plan_path',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    // ========== KAPCSOLATOK ==========

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }
}
