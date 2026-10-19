<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unifica la autenticación en la tabla `usuarios`.
 *
 * Todas las claves foráneas del dominio (movimientos, bajas, mantenimientos,
 * documentos, requerimientos y auditoría) apuntan a `usuarios`, mientras que la
 * tabla `users` de Laravel nunca se usó (está vacía). Se completan en `usuarios`
 * las columnas que necesita la autenticación de Laravel y se elimina `users`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->rememberToken()->after('password');
            $table->boolean('debe_cambiar_password')->default(false)->after('remember_token');
            $table->timestamp('ultimo_acceso_at')->nullable()->after('estado');
            $table->foreignId('sede_id')->nullable()->after('rol_id')->constrained('sedes')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('area_id')->nullable()->after('sede_id')->constrained('areas')->cascadeOnUpdate()->nullOnDelete();
            $table->softDeletes();
        });

        Schema::dropIfExists('users');
    }

    public function down(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('area_id');
            $table->dropConstrainedForeignId('sede_id');
            $table->dropSoftDeletes();
            $table->dropColumn(['remember_token', 'debe_cambiar_password', 'ultimo_acceso_at']);
        });
    }
};
