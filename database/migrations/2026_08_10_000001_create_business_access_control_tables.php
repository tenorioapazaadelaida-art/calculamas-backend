<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('legal_name', 200)->nullable();
            $table->string('nit', 30)->nullable();
            $table->string('business_type', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->char('currency', 3)->default('BOB');
            $table->string('timezone', 60)->default('America/La_Paz');
            $table->boolean('taxes_enabled')->default(false);
            $table->string('tax_regime', 80)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('business_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->boolean('active')->default(true)->after('password');
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('slug', 80);
            $table->boolean('system')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['business_id', 'slug']);
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->string('module', 60);
            $table->string('action', 60);
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });

        Schema::create('permission_user', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('allowed')->default(true);
            $table->primary(['permission_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_user');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_id');
            $table->dropColumn('active');
        });
        Schema::dropIfExists('businesses');
    }
};
