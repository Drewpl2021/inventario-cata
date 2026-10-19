<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flujo de bajas:
 * - El estado por defecto pasa a ser 'solicitada'.
 * - Se quita el UNIQUE de `bien_id` para que una baja rechazada pueda volver a
 *   solicitarse (la regla "una sola baja activa por bien" se valida en la app).
 * - Se registra quién resolvió la solicitud y cuándo.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Los nombres reales dependen de cómo se creó la tabla (migración o script SQL),
        // así que se leen del esquema en lugar de asumirlos.
        $foraneaBien = collect(Schema::getForeignKeys('bajas'))
            ->first(fn (array $fk) => $fk['columns'] === ['bien_id'])['name'] ?? null;
        $unicoBien = collect(Schema::getIndexes('bajas'))
            ->first(fn (array $index) => $index['unique'] && ! $index['primary'] && $index['columns'] === ['bien_id'])['name'] ?? null;

        Schema::table('bajas', function (Blueprint $table) use ($foraneaBien, $unicoBien) {
            if ($foraneaBien) {
                $table->dropForeign($foraneaBien);
            }
            if ($unicoBien) {
                $table->dropUnique($unicoBien);
            }
        });

        Schema::table('bajas', function (Blueprint $table) {
            $table->index('bien_id', 'idx_baja_bien');
            $table->foreign('bien_id')->references('id')->on('bienes')->restrictOnDelete();

            $table->enum('estado', ['solicitada', 'aprobada', 'rechazada'])->default('solicitada')->change();
            $table->index('estado', 'idx_baja_estado');

            $table->foreignId('aprobado_por')->nullable()->after('usuario_id')
                ->constrained('usuarios')->cascadeOnUpdate()->nullOnDelete();
            $table->dateTime('fecha_resolucion')->nullable()->after('aprobado_por');
        });
    }

    /**
     * Nota: si existen varias bajas del mismo bien (por ejemplo, una rechazada y
     * otra nueva), restaurar el UNIQUE fallará; hay que depurarlas antes.
     */
    public function down(): void
    {
        Schema::table('bajas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('aprobado_por');
            $table->dropColumn('fecha_resolucion');
            $table->dropIndex('idx_baja_estado');
            $table->enum('estado', ['solicitada', 'aprobada', 'rechazada'])->default('aprobada')->change();

            $table->dropForeign(['bien_id']);
            $table->dropIndex('idx_baja_bien');
        });

        Schema::table('bajas', function (Blueprint $table) {
            $table->unique('bien_id');
            $table->foreign('bien_id')->references('id')->on('bienes')->restrictOnDelete();
        });
    }
};
