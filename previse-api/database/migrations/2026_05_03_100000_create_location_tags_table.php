<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ML2.3: Helyszín-címke katalógus (org-specifikus).
 *
 * Az adminok szabadon felvehetnek címkéket színkóddal együtt
 * (pl. "VIP", "Lift hibás", "Új", "Bejárás szükséges").
 * A `color` mező egy tailwind színkulcs (slate, red, orange, … pink),
 * a frontend egyetlen mapping táblából rendel hozzá osztályokat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_tags', function (Blueprint $table) {
            $table->id()->comment('Elsődleges kulcs');
            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('Tulajdonos szervezet — a katalógus org-specifikus');
            $table->string('name', 50)->comment('A címke megjelenítendő neve');
            $table->string('color', 20)->default('teal')->comment('Tailwind színkulcs (slate, red, blue, teal, …); a frontend mapping-ből rendeli hozzá az osztályokat');
            $table->unsignedSmallInteger('sort_order')->default(0)->comment('Megjelenítési sorrend a katalógusban (0 = legelöl)');
            $table->timestamps();

            $table->index(['organization_id', 'sort_order']);
            $table->unique(['organization_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_tags');
    }
};
