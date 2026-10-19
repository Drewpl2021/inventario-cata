<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para las consultas más frecuentes:
 * - Historial del bien (auditoría por tabla y registro).
 * - Bienes esperados de una toma de inventario y listado filtrado por sede y estado.
 * - Orden por nombre en el listado y el buscador global.
 * - Lectura vigente de un código dentro de una campaña de escaneo.
 * Se quita idx_bien_codigo: repetía el índice único de codigo_patrimonial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditoria', function (Blueprint $table) {
            $table->index(['tabla', 'registro_id'], 'idx_auditoria_registro');
        });

        Schema::table('bienes', function (Blueprint $table) {
            $table->index(['sede_id', 'area_id', 'estado'], 'idx_bien_ubicacion_estado');
            $table->index('nombre', 'idx_bien_nombre');
            $table->dropIndex('idx_bien_codigo');
        });

        Schema::table('inventario_detalles', function (Blueprint $table) {
            $table->index(['inventario_id', 'codigo_leido'], 'idx_detalle_inventario_codigo');
        });
    }

    public function down(): void
    {
        Schema::table('inventario_detalles', function (Blueprint $table) {
            $table->dropIndex('idx_detalle_inventario_codigo');
        });

        Schema::table('bienes', function (Blueprint $table) {
            $table->index('codigo_patrimonial', 'idx_bien_codigo');
            $table->dropIndex('idx_bien_nombre');
            $table->dropIndex('idx_bien_ubicacion_estado');
        });

        Schema::table('auditoria', function (Blueprint $table) {
            $table->dropIndex('idx_auditoria_registro');
        });
    }
};
