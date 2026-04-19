<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * User (személyes) adatok.
 *
 * Ez a resource CSAK a user-szintű (szervezet-független) adatokat tartalmazza.
 * A membership-specifikus adatok (szerepkör, engedélyek, szervezet)
 * külön részében adjuk vissza a megfelelő válaszokban.
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_path ? asset('storage/' . $this->avatar_path) : null,
            'initials' => $this->initials,
            'is_active' => $this->is_active,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'pending_email' => $this->pending_email,
            'two_factor_enabled' => $this->hasTwoFactorEnabled(),
            'scheduled_deletion_at' => $this->scheduled_deletion_at?->toIso8601String(),
            'days_until_deletion' => $this->daysUntilDeletion(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'settings' => $this->when(
                $this->relationLoaded('settings') || $request->routeIs('auth.user', 'auth.login'),
                fn () => $this->settings ? [
                    'theme' => $this->settings->theme,
                    'color_scheme' => $this->settings->color_scheme,
                    'locale' => $this->settings->locale,
                    'timezone' => $this->settings->timezone,
                    'items_per_page' => $this->settings->items_per_page,
                    'default_organization_id' => $this->settings->default_organization_id,
                    'lockscreen_timeout_minutes' => $this->settings->lockscreen_timeout_minutes,
                    'notification_email' => $this->settings->notification_email,
                    'notification_push' => $this->settings->notification_push,
                    'notification_sound' => $this->settings->notification_sound,
                ] : null
            ),
        ];
    }
}
