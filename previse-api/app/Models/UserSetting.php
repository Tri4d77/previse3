<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    protected $fillable = [
        'user_id',
        'theme',
        'color_scheme',
        'locale',
        'timezone',
        'items_per_page',
        'locations_view',
        'default_organization_id',
        'lockscreen_timeout_minutes',
        'notification_email',
        'notification_push',
        'notification_sound',
    ];

    protected $casts = [
        'items_per_page' => 'integer',
        'lockscreen_timeout_minutes' => 'integer',
        'notification_email' => 'boolean',
        'notification_push' => 'boolean',
        'notification_sound' => 'boolean',
    ];

    // ========== KAPCSOLATOK ==========

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function defaultOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'default_organization_id');
    }
}
