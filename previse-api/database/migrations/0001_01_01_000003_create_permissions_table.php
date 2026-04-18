<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ========== PERMISSIONS ==========
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('module', 100);
            $table->string('action', 100);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['module', 'action']);
        });

        // ========== ROLE_PERMISSION (pivot) ==========
        Schema::create('role_permission', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();

            $table->primary(['role_id', 'permission_id']);
        });

        // ========== GROUPS ==========
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // ========== GROUP_MEMBERSHIP (pivot - csoport ↔ tagság) ==========
        Schema::create('group_membership', function (Blueprint $table) {
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();

            $table->primary(['group_id', 'membership_id']);
        });

        // ========== ALLOWED DOMAINS ==========
        Schema::create('allowed_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->timestamp('created_at')->nullable();

            $table->unique(['organization_id', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allowed_domains');
        Schema::dropIfExists('group_membership');
        Schema::dropIfExists('groups');
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('permissions');
    }
};
