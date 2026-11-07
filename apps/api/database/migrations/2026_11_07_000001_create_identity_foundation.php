<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->ulid('public_id')->unique();
            $table->boolean('active')->default(true);
            $table->boolean('is_demo')->default(false);
        });
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('legal_name');
            $table->string('trade_name');
            $table->string('timezone')->default('America/Sao_Paulo');
            $table->boolean('active')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
        });
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->foreignId('permission_id')->constrained()->restrictOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });
        // Nome explícito acompanha o contrato lógico, inclusive múltiplos papéis por empresa.
        Schema::create('company_users', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->primary(['company_id', 'user_id', 'role_id']);
            $table->index(['user_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_users');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('companies');
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn(['public_id', 'active', 'is_demo']);
        });
    }
};
