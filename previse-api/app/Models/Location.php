<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Helyszín (épület).
 *
 * is_active értelmezése:
 *  - STATE_ACTIVE     (1) — aktív, mindenhol megjelenik (default)
 *  - STATE_ARCHIVED   (0) — csak az „archív megjelenítése" pipa-bekapcsolásával látszik
 *  - STATE_TERMINATED (2) — megszűnt, ezzel jelöljük, hogy lezárt épület
 */
class Location extends Model
{
    use SoftDeletes;

    public const STATE_ARCHIVED = 0;
    public const STATE_ACTIVE = 1;
    public const STATE_TERMINATED = 2;

    public const STATES = [
        self::STATE_ARCHIVED,
        self::STATE_ACTIVE,
        self::STATE_TERMINATED,
    ];

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'type_id',
        'address',
        'city',
        'zip_code',
        'latitude',
        'longitude',
        'description',
        'image_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'is_active' => 'integer',
        ];
    }

    // ========== KAPCSOLATOK ==========

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LocationType::class, 'type_id');
    }

    public function floors(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Floor::class)->orderBy('sort_order')->orderBy('level');
    }

    /**
     * Az összes helyiség a helyszínen (szinttel és anélkül is).
     */
    public function rooms(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * Csak a szint nélküli helyiségek (közvetlen a Locationhoz).
     */
    public function unassignedRooms(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Room::class)->whereNull('floor_id');
    }

    /**
     * Külső kontaktok (ML2.2).
     */
    public function contacts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LocationContact::class)->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Belső felelősök — a saját szervezet membership-jei (ML2.2).
     */
    public function responsibles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Membership::class, 'location_responsibles')
            ->withPivot('assigned_at');
    }

    /**
     * Hozzárendelt címkék (ML2.3).
     */
    public function tags(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(LocationTag::class, 'location_tag_assignments', 'location_id', 'tag_id')
            ->orderBy('location_tags.sort_order')
            ->orderBy('location_tags.name');
    }

    // ========== HELPER METÓDUSOK ==========

    public function isActive(): bool
    {
        return $this->is_active === self::STATE_ACTIVE;
    }

    public function isArchived(): bool
    {
        return $this->is_active === self::STATE_ARCHIVED;
    }

    public function isTerminated(): bool
    {
        return $this->is_active === self::STATE_TERMINATED;
    }

    /**
     * Publikus URL a fő-fotóhoz (storage:link → public/storage).
     */
    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }
        return Storage::url($this->image_path);
    }

    /**
     * Következő, még szabad code generálása az adott szervezeten belül.
     * Formátum: "LOC-001", "LOC-002", …
     *
     * Akkor használjuk, ha a felhasználó nem ad meg saját kódot.
     */
    public static function generateNextCode(int $organizationId): string
    {
        $highestCode = static::query()
            ->where('organization_id', $organizationId)
            ->where('code', 'like', 'LOC-%')
            ->withTrashed()
            ->orderByDesc('code')
            ->value('code');

        if (! $highestCode) {
            return 'LOC-001';
        }

        $number = (int) substr($highestCode, 4);
        return sprintf('LOC-%03d', $number + 1);
    }
}
