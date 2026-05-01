<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ML1: Helyszínek (épületek) — alap CRUD.
 *
 * Subscriber-tulajdonú: organization_id mindig egy subscriber szervezet.
 * code: org-szinten egyedi azonosító, importnál a sheet-ek közti
 *       összekötéshez használjuk.
 * is_active: TINYINT (1=aktív, 0=archív, 2=megszűnt).
 *
 * Floors/Rooms külön migrationben (ML2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->string('code', 50);                                  // egyedi org-szinten
            $table->string('name', 255);
            $table->foreignId('type_id')->nullable()->constrained('location_types')->nullOnDelete();

            // Cím
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('zip_code', 20)->nullable();

            // GPS (8 tizedes pontosság ~ 1 mm a szélesség és ~ cm a hosszúság szerint)
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            $table->text('description')->nullable();

            // Fő-fotó (egyetlen kép a kártya nézethez)
            $table->string('image_path', 500)->nullable();

            // 1 = aktív (default), 0 = archív, 2 = megszűnt
            $table->unsignedTinyInteger('is_active')->default(1);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'is_active']);
            $table->index('type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
