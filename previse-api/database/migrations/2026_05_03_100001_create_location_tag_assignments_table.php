<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ML2.3: Helyszín ↔ címke pivot.
 *
 * Egy helyszínhez 0..N címke rendelhető. A pivotnak nincsenek timestamps-ai,
 * a sorrendet a tag.sort_order adja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_tag_assignments', function (Blueprint $table) {
            $table->id()->comment('Elsődleges kulcs');
            $table->foreignId('location_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('Helyszín — törlésekor a hozzárendelés is törlődik');
            $table->foreignId('tag_id')
                ->constrained('location_tags')
                ->cascadeOnDelete()
                ->comment('Címke — törlésekor a hozzárendelés is törlődik');

            $table->unique(['location_id', 'tag_id']);
            $table->index('tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_tag_assignments');
    }
};
