<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ML2.2: Helyszín-felelősök pivot tábla.
 *
 * A saját szervezet (subscriber) tagjai (membership-jei) közül jelölhető ki
 * 0..N felelős egy helyszínért. Több-több kapcsolat: egy user akár több
 * helyszínért is felelős lehet, és egy helyszínnek több felelőse is van.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_responsibles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at')->useCurrent();

            $table->unique(['location_id', 'membership_id']);
            $table->index('membership_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_responsibles');
    }
};
