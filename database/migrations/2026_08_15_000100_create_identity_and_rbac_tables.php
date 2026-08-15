<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('nom');
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 150)->unique();
            $table->string('nom');
            $table->string('domaine', 100)->index();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('roles_utilisateurs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->foreignId('utilisateur_id')->constrained('utilisateurs')->cascadeOnDelete();
            $table->foreignId('attribue_par_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->timestampTz('attribue_le')->useCurrent();
            $table->timestampTz('expire_le')->nullable();
            $table->timestamps();
            $table->unique(['role_id', 'utilisateur_id']);
        });

        Schema::create('permissions_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('permission_id')->constrained('permissions')->restrictOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['permission_id', 'role_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE roles_utilisateurs ADD CONSTRAINT roles_utilisateurs_dates_valides CHECK (expire_le IS NULL OR expire_le > attribue_le)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions_roles');
        Schema::dropIfExists('roles_utilisateurs');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
