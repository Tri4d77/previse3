<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();

            // Saját bővítések:
            // - current_membership_id: normál user esetén melyik tagsággal dolgozik
            // - context_organization_id: szuper-admin impersonation esetén melyik szervezet kontextusában
            $table->foreignId('current_membership_id')->nullable();
            $table->foreignId('context_organization_id')->nullable();

            $table->timestamps();

            $table->index('current_membership_id');
            $table->index('context_organization_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
