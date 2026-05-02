<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Helyszín-kontakt: külső személy a helyszín kapcsán
 * (pl. portás, műszaki vezető, igazgató).
 *
 * NEM rendszerhasználó — csak adatok. Ha a kontakt véletlenül egy
 * rendszerhasználó is, az `email` mezőre építhető a kapcsolat, de
 * automatikus "linkelést" most nem csinálunk.
 */
class LocationContact extends Model
{
    protected $fillable = [
        'location_id',
        'name',
        'role_label',
        'phone',
        'email',
        'note',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
