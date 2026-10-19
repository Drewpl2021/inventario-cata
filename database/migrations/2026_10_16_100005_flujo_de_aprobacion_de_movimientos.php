<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flujo de solicitud de movimientos:
 * solicitado → aprobado (se actualiza el bien) → ejecutado, o solicitado → rechazado.
 *
 * Los movimientos que ya existían se aplicaron directamente sobre el bien, así
 * que se marcan como 'ejecutado'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->enum('estado', ['solicitado', 'aprobado', 'rechazado', 'ejecutado'])->default('solicitado')->after('tipo');
            $table->foreignId('aprobado_por')->nullable()->after('usuario_id')
                ->constrained('usuarios')->cascadeOnUpdate()->nullOnDelete();
            $table->dateTime('fecha_resolucion')->nullable()->after('aprobado_por');
            $table->text('motivo_rechazo')->nullable()->after('fecha_resolucion');

            $table->index('estado', 'idx_movimiento_estado');
        });

        DB::table('movimientos')->update(['estado' => 'ejecutado']);
    }

    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropIndex('idx_movimiento_estado');
            $table->dropConstrainedForeignId('aprobado_por');
            $table->dropColumn(['estado', 'fecha_resolucion', 'motivo_rechazo']);
        });
    }
};
