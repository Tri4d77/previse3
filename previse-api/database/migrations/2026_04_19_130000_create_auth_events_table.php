<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M8: Auth események napló.
 *
 * Minden autentikáció-/biztonság-releváns eseményt idetárolunk (login, password change,
 * 2FA műveletek, session revoke, fiók-törlés kezdeményezés/visszavonás, szervezetből kilépés,
 * super-admin impersonation, stb.).
 *
 * Retention: 90 nap (pruning parancs M8 része).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Ha a user nem azonosítható (pl. rossz email-re login attempt), email külön is itt:
            $table->string('email', 255)->nullable();
            $table->string('event', 64);           // 'login_success', 'login_failed', stb.
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('metadata')->nullable();  // kontextuális adatok (org_id, reason, stb.)
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id');
            $table->index('event');
            $table->index('created_at');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_events');
    }
};
