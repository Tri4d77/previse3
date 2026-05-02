<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ML2.2: Helyszín-kontaktok (külső személyek).
 *
 * Egy helyszínhez 0..N külső kontakt rendelhető. Ezek NEM rendszerhasználók
 * (saját user-rekordjuk nincs), csak adatok: név, szerep-felirat
 * (szabad-szöveg, autocomplete a már használt értékekből), telefon, email,
 * megjegyzés.
 *
 * Példa: "Műszaki vezető", "Portás", "Karbantartó 1", "Üzemeltetési igazgató".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('role_label', 100)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->text('note')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['location_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_contacts');
    }
};
