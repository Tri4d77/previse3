<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Helyiség-típus katalógus (org-szintű).
 *
 * Lásd `location_types` — hasonló szerkezet. A katalógus értékeit a
 * RoomFormModal dropdown használja, de a `rooms.type` mező továbbra is
 * szabad-szöveg (érték-másolás): ha törlünk egy típust a katalógusból,
 * a meglévő helyiségek érintetlenek maradnak.
 */
class RoomType extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
