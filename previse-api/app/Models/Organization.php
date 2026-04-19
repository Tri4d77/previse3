<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Organization extends Model
{
    // Szervezet lehetséges státuszai
    public const STATUS_ACTIVE = 'active';      // Aktív
    public const STATUS_INACTIVE = 'inactive';  // Átmenetileg inaktív
    public const STATUS_TERMINATED = 'terminated'; // Megszűnt (pl. előfizetés lejárt)

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_TERMINATED,
    ];

    protected $fillable = [
        'parent_id',
        'type',
        'name',
        'slug',
        'address',
        'city',
        'zip_code',
        'phone',
        'email',
        'tax_number',
        'logo_path',
        'settings',
        'status',
        'is_active',
        'terminated_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'terminated_at' => 'datetime',
    ];

    /**
     * Slug automatikus generálása a névből, ha nincs megadva.
     */
    protected static function booted(): void
    {
        static::creating(function (Organization $org) {
            if (empty($org->slug)) {
                $org->slug = Str::slug($org->name);

                // Egyediség biztosítása
                $originalSlug = $org->slug;
                $counter = 1;
                while (static::where('slug', $org->slug)->exists()) {
                    $org->slug = $originalSlug . '-' . $counter++;
                }
            }
        });
    }

    // ========== KAPCSOLATOK ==========

    /**
     * Szülő szervezet (ügyfél → előfizető).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'parent_id');
    }

    /**
     * Gyerek szervezetek (előfizető → ügyfelei).
     */
    public function children(): HasMany
    {
        return $this->hasMany(Organization::class, 'parent_id');
    }

    /**
     * Ügyfél-szervezetek (alias a children-re, csak client típusúak).
     */
    public function clients(): HasMany
    {
        return $this->children()->where('type', 'client');
    }

    /**
     * Szervezet tagságai (memberships).
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Aktív tagságok (user-ek a membership-en keresztül).
     */
    public function activeMemberships(): HasMany
    {
        return $this->hasMany(Membership::class)
            ->where('is_active', true)
            ->whereNotNull('joined_at');
    }

    /**
     * Szervezet szerepkörei.
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /**
     * Szervezet csoportjai.
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    /**
     * Engedélyezett email domain-ek.
     */
    public function allowedDomains(): HasMany
    {
        return $this->hasMany(AllowedDomain::class);
    }

    // ========== SEGÉD METÓDUSOK ==========

    public function isPlatform(): bool
    {
        return $this->type === 'platform';
    }

    public function isSubscriber(): bool
    {
        return $this->type === 'subscriber';
    }

    public function isClient(): bool
    {
        return $this->type === 'client';
    }

    public function isTerminated(): bool
    {
        return $this->status === self::STATUS_TERMINATED;
    }

    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    /**
     * Státusz beállítása - szinkronizálja az is_active és terminated_at mezőket.
     */
    public function setStatus(string $status): void
    {
        if (! in_array($status, self::STATUSES)) {
            throw new \InvalidArgumentException("Érvénytelen státusz: {$status}");
        }

        $this->status = $status;
        $this->is_active = $status === self::STATUS_ACTIVE;

        if ($status === self::STATUS_TERMINATED && ! $this->terminated_at) {
            $this->terminated_at = now();
        } elseif ($status !== self::STATUS_TERMINATED) {
            $this->terminated_at = null;
        }

        $this->save();
    }

    /**
     * Ellenőrzi, hogy egy email domain engedélyezett-e.
     * Ha nincs domain korlátozás, mindent elfogad.
     */
    public function isEmailDomainAllowed(string $email): bool
    {
        $domains = $this->allowedDomains;

        if ($domains->isEmpty()) {
            return true; // Nincs korlátozás
        }

        $emailDomain = Str::after($email, '@');

        return $domains->contains('domain', $emailDomain);
    }
}
