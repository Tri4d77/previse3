<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Helyszín-címke (org-specifikus katalógus tétel).
 */
class LocationTag extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'color',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * Engedélyezett színek (tailwind kulcsok). A frontend ugyanezt használja.
     */
    public const ALLOWED_COLORS = [
        'slate', 'gray', 'red', 'orange', 'amber', 'yellow',
        'lime', 'green', 'teal', 'cyan', 'blue', 'indigo',
        'violet', 'purple', 'pink', 'rose',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'location_tag_assignments', 'tag_id', 'location_id');
    }
}
