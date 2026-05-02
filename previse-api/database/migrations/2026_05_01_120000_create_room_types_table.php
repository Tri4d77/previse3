<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ML2.1 finalizálás: org-szintű helyiség-típus katalógus.
 *
 * A `rooms.type` mező marad VARCHAR(100) a DB-ben (érték-másolás), de a UI
 * dropdown ennek a katalógusnak az értékeiből választ. Ha később törlik a
 * típust a katalógusból, a meglévő helyiségek nem törlődnek.
 *
 * Hasonló szerkezetű, mint a `location_types`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
            $table->index(['organization_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_types');
    }
};
