<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registra quién resolvió (aprobó o rechazó) cada requerimiento y cuándo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requerimientos', function (Blueprint $table) {
            $table->foreignId('aprobado_por')->nullable()->after('usuario_id')
                ->constrained('usuarios')->cascadeOnUpdate()->nullOnDelete();
            $table->dateTime('fecha_resolucion')->nullable()->after('motivo_rechazo');
        });
    }

    public function down(): void
    {
        Schema::table('requerimientos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('aprobado_por');
            $table->dropColumn('fecha_resolucion');
        });
    }
};
