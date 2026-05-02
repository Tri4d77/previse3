<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ML2.1: Szintek (floors) tábla.
 *
 * Egy helyszínhez 0..N szint tartozhat. A `level` numerikus érték
 * (rendezéshez): -2, -1, 0 (földszint), 1, 2, ... A `name` szabad
 * szöveg ("Földszint", "1. emelet", "B2 pince").
 *
 * `floor_plan_path` az alaprajz fájl, amit ML3-ban implementálunk —
 * az oszlop most már elkészül, hogy ne kelljen későbbi migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('floors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->integer('level')->default(0);
            $table->text('description')->nullable();
            $table->string('floor_plan_path', 500)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['location_id', 'level']);
            $table->index(['location_id', 'sort_order']);
            // Egy szervezeten belül egy épületen a szintnév egyedi
            $table->unique(['location_id', 'name'], 'floors_location_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('floors');
    }
};
