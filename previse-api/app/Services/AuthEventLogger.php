<?php

namespace App\Services;

use App\Models\AuthEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Autentikáció és biztonsági események naplózása.
 *
 * A controllerek és service-ek egy helyen kérik a logger-t, és csak
 * az eseménynevet + metadatát adják meg.  Az IP / user agent az aktuális
 * request-ből automatikusan feltöltődik.
 *
 * A try/catch biztosítja, hogy egy naplózási hiba ne akadályozzon meg
 * semmilyen fő műveletet.
 */
class AuthEventLogger
{
    public function __construct(
        private ?Request $request = null,
    ) {
        $this->request ??= request();
    }

    /**
     * Általános napló.
     */
    public function log(
        string $event,
        ?User $user = null,
        ?string $email = null,
        array $metadata = [],
    ): ?AuthEvent {
        try {
            return AuthEvent::create([
                'user_id' => $user?->id,
                'email' => $email ?? $user?->email,
                'event' => $event,
                'ip_address' => $this->request?->ip(),
                'user_agent' => $this->truncate((string) ($this->request?->userAgent() ?? ''), 500),
                'metadata' => $metadata ?: null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AuthEventLogger failed', [
                'event' => $event,
                'user_id' => $user?->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function truncate(string $value, int $max): ?string
    {
        if ($value === '') return null;
        return mb_substr($value, 0, $max);
    }
}
