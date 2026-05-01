<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ML1: Helyszín-típusok (org-specifikus katalógus).
 *
 * Minden subscriber szervezet saját maga szerkesztheti a típus-listát
 * (Iroda, Bevásárlóközpont, Lakóház, …). Default seederből alaplista jön.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'sort_order']);
            $table->unique(['organization_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_types');
    }
};
