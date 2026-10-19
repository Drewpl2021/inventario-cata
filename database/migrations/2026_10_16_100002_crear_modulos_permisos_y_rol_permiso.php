<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sistema propio de roles y permisos (módulo × acción).
 *
 * Además de las columnas pedidas, `modulos` guarda `ruta` (nombre de la ruta de
 * Laravel a la que lleva el menú) y `grupo` (sección del sidebar), para poder
 * armar el menú lateral directamente desde la base de datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('es_sistema')->default(false)->after('descripcion');
            $table->enum('estado', ['activo', 'inactivo'])->default('activo')->after('es_sistema');
        });

        Schema::create('modulos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('slug', 50)->unique();
            $table->string('icono', 60)->nullable();
            $table->string('ruta', 100)->nullable();
            $table->string('grupo', 50)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->timestamps();
        });

        Schema::create('permisos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modulo_id')->constrained('modulos')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('accion', ['ver', 'crear', 'editar', 'eliminar', 'aprobar', 'exportar', 'imprimir']);
            $table->string('slug', 80)->unique();
            $table->timestamps();

            $table->unique(['modulo_id', 'accion'], 'uk_permiso_modulo_accion');
        });

        Schema::create('rol_permiso', function (Blueprint $table) {
            $table->foreignId('rol_id')->constrained('roles')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('permiso_id')->constrained('permisos')->cascadeOnUpdate()->cascadeOnDelete();

            $table->primary(['rol_id', 'permiso_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rol_permiso');
        Schema::dropIfExists('permisos');
        Schema::dropIfExists('modulos');

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['es_sistema', 'estado']);
        });
    }
};
