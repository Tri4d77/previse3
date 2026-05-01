<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ML1: Lista vs. kártya nézet beállítása user-szinten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->string('locations_view', 10)->default('list')->after('items_per_page');
            // értékek: 'list' (default) | 'cards'
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->dropColumn('locations_view');
        });
    }
};
