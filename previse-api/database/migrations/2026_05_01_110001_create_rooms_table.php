<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ML2.1: Helyiségek (rooms) tábla.
 *
 * Rugalmas hierarchia (lásd docs/07-modules-facility.md 1.2):
 *   - location_id: KÖTELEZŐ — a helyiség mindig egy épülethez tartozik
 *   - floor_id: NULLABLE — szint nélküli helyiség is megengedett
 *
 * `room_plan_path` az ML3-as alaprajz mező, most már elkészül.
 *
 * A `type` szabad-szöveg + frontend-en autocomplete a már használt értékekből.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('floor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 255);
            $table->string('number', 50)->nullable();
            $table->string('type', 100)->nullable();
            $table->decimal('area_sqm', 10, 2)->nullable();
            $table->text('description')->nullable();
            $table->string('room_plan_path', 500)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['location_id', 'floor_id']);
            $table->index(['location_id', 'sort_order']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
