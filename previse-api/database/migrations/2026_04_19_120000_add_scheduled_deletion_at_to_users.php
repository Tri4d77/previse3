<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M7: Fiók megszüntetése (30 napos grace period).
 *
 * A `scheduled_deletion_at` kitöltése jelzi, hogy a user kérte a fiók törlését.
 * A napi `users:finalize-deletions` parancs ezt figyeli, és ha lejárt, anonimizálja a fiókot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('scheduled_deletion_at')->nullable()->after('last_login_ip');
            $table->index('scheduled_deletion_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['scheduled_deletion_at']);
            $table->dropColumn('scheduled_deletion_at');
        });
    }
};
