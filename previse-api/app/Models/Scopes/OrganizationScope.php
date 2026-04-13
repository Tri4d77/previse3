<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Szervezet-szintű szűrés (multi-tenant).
 *
 * Automatikusan szűri a lekérdezéseket a bejelentkezett felhasználó
 * szervezetéhez. Így egy szervezet soha nem látja a másik adatait.
 *
 * Használat a Model-ben:
 *   protected static function booted(): void
 *   {
 *       static::addGlobalScope(new OrganizationScope);
 *   }
 *
 * Platform (szuper-admin) felhasználók: NEM szűr (mindent látnak).
 */
class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        // Szuper-admin mindent lát
        if ($user->isSuperAdmin()) {
            return;
        }

        // Előfizető: a saját szervezetét ÉS az ügyfeleit is látja
        if ($user->organization->isSubscriber()) {
            $builder->where(function (Builder $query) use ($user, $model) {
                if ($model->getTable() === 'organizations') {
                    // Organizations táblánál: saját + gyerekek
                    $query->where('id', $user->organization_id)
                        ->orWhere('parent_id', $user->organization_id);
                } else {
                    // Más tábláknál: saját szervezet adatai
                    $query->where($model->getTable() . '.organization_id', $user->organization_id);
                }
            });
            return;
        }

        // Ügyfél szervezet: csak a sajátját látja
        $builder->where($model->getTable() . '.organization_id', $user->organization_id);
    }
}
