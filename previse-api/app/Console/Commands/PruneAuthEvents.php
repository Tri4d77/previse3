<?php

namespace App\Console\Commands;

use App\Models\AuthEvent;
use Illuminate\Console\Command;

/**
 * Auth napló régi rekordok törlése (retention policy szerint).
 *
 * Kézi futtatás:
 *   docker exec previse-app php artisan auth:prune-events
 *
 * Scheduler-be M10-ben kerül (napi 1x).
 */
class PruneAuthEvents extends Command
{
    protected $signature = 'auth:prune-events
                            {--days= : Felülírja a config/auth.php auth_events_retention_days értékét}
                            {--dry-run : Csak jelzi, mit törölne}';

    protected $description = 'Retention policy-t meghaladó auth események törlése.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('auth.auth_events_retention_days', 90));
        $cutoff = now()->subDays($days);

        $query = AuthEvent::where('created_at', '<', $cutoff);
        $count = $query->count();

        if ($count === 0) {
            $this->info("Nincs törölni való auth esemény ({$days} napnál régebbi).");
            return self::SUCCESS;
        }

        $this->info("Törlendő auth események: {$count} db (régebbi, mint " . $cutoff->format('Y-m-d') . ').');

        if ($this->option('dry-run')) {
            $this->warn('--dry-run: nincs változtatás.');
            return self::SUCCESS;
        }

        $deleted = $query->delete();
        $this->info("✓ {$deleted} rekord törölve.");

        return self::SUCCESS;
    }
}
