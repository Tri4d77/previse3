<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ========== ORGANIZATIONS ==========
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->enum('type', ['platform', 'subscriber', 'client']);
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('zip_code', 20)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('tax_number', 50)->nullable();
            $table->string('logo_path', 500)->nullable();
            $table->json('settings')->nullable();
            // status: active (aktív), inactive (átmenetileg inaktív), terminated (megszűnt/előfizetés lejárt)
            $table->string('status', 20)->default('active');
            $table->boolean('is_active')->default(true); // computed from status, marad backward compat miatt
            $table->timestamp('terminated_at')->nullable();
            $table->timestamps();

            $table->index('parent_id');
            $table->index('type');
            $table->index('is_active');
            $table->index('status');
        });

        // ========== ROLES ==========
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
        });

        // ========== USERS (user = személy, szervezet-független) ==========
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable(); // Lehet NULL meghívott usernél, amíg be nem állítja
            $table->string('avatar_path', 500)->nullable();
            $table->string('phone', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('email_verified_at')->nullable();

            // Email-változtatás flow
            $table->string('pending_email')->nullable();
            $table->string('email_change_token', 100)->nullable()->index();
            $table->timestamp('email_change_sent_at')->nullable();

            // 2FA
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            // Bejelentkezés követés
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });

        // ========== USER SETTINGS ==========
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('theme', 20)->default('system');
            $table->string('color_scheme', 20)->default('teal');
            $table->string('locale', 10)->default('hu');
            $table->string('timezone', 50)->default('Europe/Budapest');
            $table->unsignedSmallInteger('items_per_page')->default(25);
            $table->foreignId('default_organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->unsignedSmallInteger('lockscreen_timeout_minutes')->default(30); // 0 = soha
            $table->boolean('notification_email')->default(true);
            $table->boolean('notification_push')->default(true);
            $table->boolean('notification_sound')->default(true);
            $table->timestamps();
        });

        // ========== MEMBERSHIPS (user ↔ organization ↔ role) ==========
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('invitation_token', 100)->nullable()->index();
            $table->timestamp('invitation_sent_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('last_active_at')->nullable();

            // Szervezet elhagyásához token
            $table->string('leave_token', 100)->nullable()->index();
            $table->timestamp('leave_sent_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Aktív tagság egyedi a (user, organization) páronként
            $table->index(['user_id', 'organization_id']);
            $table->index('is_active');
        });

        // ========== PASSWORD RESET TOKENS ==========
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('user_settings');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('organizations');
    }
};
