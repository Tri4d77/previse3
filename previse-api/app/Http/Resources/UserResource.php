<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Felhasználó adatai az API válaszban.
     */
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
            'two_factor_enabled' => $this->hasTwoFactorEnabled(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'role' => [
                'id' => $this->role->id,
                'name' => $this->role->name,
                'slug' => $this->role->slug,
            ],
            'organization' => [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
                'type' => $this->organization->type,
                'slug' => $this->organization->slug,
            ],
            'permissions' => $this->when(
                $request->routeIs('auth.user', 'auth.login'),
                fn () => $this->role->permissions->map(fn ($p) => $p->module . '.' . $p->action)->values()
            ),
            'settings' => $this->when(
                $request->routeIs('auth.user', 'auth.login'),
                fn () => $this->getOrCreateSettings()->only([
                    'theme', 'color_scheme', 'locale', 'timezone',
                    'items_per_page', 'default_page',
                    'notification_email', 'notification_push', 'notification_sound',
                ])
            ),
            'groups' => $this->when(
                $this->relationLoaded('groups'),
                fn () => $this->groups->map(fn ($g) => ['id' => $g->id, 'name' => $g->name])
            ),
        ];
    }
}
