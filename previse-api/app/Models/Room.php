<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Helyiség (egy helyszínhez kötelezően, szinthez opcionálisan).
 */
class Room extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'location_id',
        'floor_id',
        'name',
        'number',
        'type',
        'area_sqm',
        'description',
        'room_plan_path',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'area_sqm' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    // ========== KAPCSOLATOK ==========

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }
}
