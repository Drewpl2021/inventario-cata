<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Toma de inventario por escaneo.
 *
 * - `inventarios`: campaña por sede (y opcionalmente área). La columna generada
 *   `clave_abierta` solo tiene valor mientras la campaña está abierta y es única:
 *   la base de datos impide dos campañas abiertas para la misma sede/área.
 * - `inventario_responsables`: quiénes realizan el conteo.
 * - `inventario_detalles`: cada lectura. `clave_lectura` (generada y única) impide
 *   registrar dos veces el mismo código en una campaña, aunque varios usuarios
 *   escaneen a la vez; al deshacer una lectura queda en NULL y se puede volver a
 *   leer. `uuid_cliente` hace idempotente la sincronización de lecturas offline.
 * - `movimientos.inventario_id` y `bajas.inventario_id`: solicitudes generadas
 *   desde el resultado de una campaña.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventarios', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 150);
            // Sin CASCADE: MySQL no lo admite en columnas que alimentan una columna generada.
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('areas')->restrictOnUpdate()->restrictOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->enum('estado', ['abierto', 'cerrado'])->default('abierto');
            $table->text('observaciones')->nullable();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnUpdate()->restrictOnDelete();
            $table->dateTime('cerrado_at')->nullable();
            $table->foreignId('cerrado_por')->nullable()->constrained('usuarios')->cascadeOnUpdate()->nullOnDelete();
            $table->unsignedInteger('total_esperados')->nullable();
            $table->timestamps();

            $table->string('clave_abierta', 40)->nullable()->unique()
                ->storedAs("IF(estado = 'abierto', CONCAT(sede_id, '-', IFNULL(area_id, 0)), NULL)");
            $table->index(['sede_id', 'estado'], 'idx_inventario_sede_estado');
        });

        Schema::create('inventario_responsables', function (Blueprint $table) {
            $table->foreignId('inventario_id')->constrained('inventarios')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnUpdate()->restrictOnDelete();

            $table->primary(['inventario_id', 'usuario_id']);
        });

        Schema::create('inventario_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventario_id')->constrained('inventarios')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('bien_id')->nullable()->constrained('bienes')->cascadeOnUpdate()->nullOnDelete();
            $table->string('codigo_leido', 80);
            $table->enum('resultado', ['encontrado', 'fuera_de_ubicacion', 'sobrante_no_registrado', 'dado_de_baja', 'faltante']);
            $table->enum('estado_fisico', ['bueno', 'regular', 'malo'])->nullable();
            $table->foreignId('area_escaneo_id')->nullable()->constrained('areas')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('escaneado_por')->nullable()->constrained('usuarios')->cascadeOnUpdate()->nullOnDelete();
            $table->dateTime('fecha_hora');
            $table->uuid('uuid_cliente')->nullable()->unique();
            $table->boolean('registrado_offline')->default(false);
            $table->dateTime('anulado_at')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('usuarios')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();

            $table->string('clave_lectura', 100)->nullable()->unique()
                ->storedAs("IF(anulado_at IS NULL, CONCAT(inventario_id, '|', codigo_leido), NULL)");
            $table->index(['inventario_id', 'resultado'], 'idx_detalle_inventario_resultado');
        });

        foreach (['movimientos', 'bajas'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->foreignId('inventario_id')->nullable()->after('bien_id')
                    ->constrained('inventarios')->cascadeOnUpdate()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['movimientos', 'bajas'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropConstrainedForeignId('inventario_id');
            });
        }

        Schema::dropIfExists('inventario_detalles');
        Schema::dropIfExists('inventario_responsables');
        Schema::dropIfExists('inventarios');
    }
};
