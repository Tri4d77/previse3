<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M4: Session- és eszközkezelés.
 *
 * A personal_access_tokens táblát kiegészítjük IP-címmel és user agent stringgel,
 * hogy a user a „Profil → Biztonság → Aktív sessionök" listán lássa, mely eszközről
 * van bejelentkezve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('context_organization_id');
            $table->string('user_agent', 500)->nullable()->after('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'user_agent']);
        });
    }
};
