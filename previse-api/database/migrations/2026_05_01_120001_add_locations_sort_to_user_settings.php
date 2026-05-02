<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ML2.1 finalizálás: user-szintű rendezési preferenciák a helyszín
 * részletoldalához (szintek és helyiségek).
 *
 *   - locations_floors_sort: 'name' | 'level' (default: 'level')
 *   - locations_rooms_sort:  'name' | 'number' | 'type' (default: 'name')
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->string('locations_floors_sort', 20)->default('level')->after('locations_view');
            $table->string('locations_rooms_sort', 20)->default('name')->after('locations_floors_sort');
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->dropColumn(['locations_floors_sort', 'locations_rooms_sort']);
        });
    }
};
