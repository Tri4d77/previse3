<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Végleges fiók-törlés: anonimizálja a lejárt grace-ű fiókokat.
 *
 * Futtatás (jelenleg kézzel, M10-ben scheduler-ezni):
 *   docker exec previse-app php artisan users:finalize-deletions
 *
 * Opciók:
 *   --dry-run      csak listázza, nem módosít
 *
 * Mit tart meg:
 *   - name           (felhasználói igény: a műszaki személyzet tudja, ki rögzítette az adatot)
 *
 * Mit töröl / reset-el:
 *   - email          → deleted-{id}@previse.local (egyedi, ne ütközzön új regisztrációkkal)
 *   - password       → 64 karakteres random (soha nem használható)
 *   - phone, avatar  → null (avatar fájl is)
 *   - is_active      → false
 *   - 2FA mezők      → null
 *   - email_change mezők, pending_email → null
 *   - last_login_at, last_login_ip → null
 *   - scheduled_deletion_at → null
 *   - user_settings  → törölve
 *   - tokenek        → törölve (már a delete endpointnál is, de biztos ami biztos)
 *   - memberships    → már soft-deleted; marad anonim nyomvonal kapcsán
 *   - users.deleted_at → most (soft delete)
 */
class FinalizeUserDeletions extends Command
{
    protected $signature = 'users:finalize-deletions {--dry-run : Csak listázza, nem módosít}';

    protected $description = 'Lejárt grace-ű fiókok anonimizálása (név megőrzésével).';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $users = User::whereNotNull('scheduled_deletion_at')
            ->where('scheduled_deletion_at', '<=', now())
            ->whereNull('deleted_at')
            ->get();

        if ($users->isEmpty()) {
            $this->info('Nincs anonimizálható fiók.');
            return self::SUCCESS;
        }

        $this->info(sprintf('%d fiók anonimizálandó:', $users->count()));
        foreach ($users as $user) {
            $this->line(sprintf('  #%d  %s  (lejárat: %s)',
                $user->id,
                $user->email,
                $user->scheduled_deletion_at->format('Y-m-d H:i'),
            ));
        }

        if ($dryRun) {
            $this->warn('--dry-run: nincs változtatás.');
            return self::SUCCESS;
        }

        foreach ($users as $user) {
            $this->anonymize($user);
            $this->info("  ✓ #{$user->id} anonimizálva");
        }

        return self::SUCCESS;
    }

    private function anonymize(User $user): void
    {
        DB::transaction(function () use ($user) {
            // Avatar fájl törlése
            if ($user->avatar_path && Storage::exists($user->avatar_path)) {
                Storage::delete($user->avatar_path);
            }

            $user->forceFill([
                // NAME marad — kérés alapján
                'email' => "deleted-{$user->id}@previse.local",
                'password' => bcrypt(Str::random(64)), // soha nem lesz használva
                'phone' => null,
                'avatar_path' => null,
                'is_active' => false,
                'email_verified_at' => null,
                'pending_email' => null,
                'email_change_token' => null,
                'email_change_sent_at' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'last_login_at' => null,
                'last_login_ip' => null,
                'scheduled_deletion_at' => null,
            ])->save();

            // Token takarítás (biztos ami biztos)
            $user->tokens()->delete();

            // Beállítások törlése
            $user->settings()->delete();

            // Soft delete
            $user->delete();
        });
    }
}
