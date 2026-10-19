<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Código de barras Code 128 de cada bien (reemplaza a `qr_codigo`).
 *
 * Formato: CAT-{prefijo de sede}-{correlativo global de 6 dígitos}, p. ej. CAT-S01-000123.
 * El correlativo es el id del bien (global, único y nunca reutilizado) y el prefijo es
 * la sede donde se registró; el código no cambia aunque el bien se traslade.
 *
 * Además:
 * - `etiqueta_impresa_at`: última impresión de la etiqueta del bien.
 * - `codigo_patrimonial` pasa a ser opcional (código oficial del colegio).
 * - Tabla `configuraciones` (clave/valor) para el tamaño y formato de la etiqueta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bienes', function (Blueprint $table) {
            $table->string('codigo_barras', 30)->nullable()->unique()->after('id');
            $table->timestamp('etiqueta_impresa_at')->nullable()->after('observaciones');
            $table->index('etiqueta_impresa_at', 'idx_bien_etiqueta_impresa');
            $table->string('codigo_patrimonial', 50)->nullable()->change();
        });

        $this->asignarCodigosExistentes();

        // El nombre del índice depende de cómo se creó la tabla (migración o script SQL).
        $indiceQr = collect(Schema::getIndexes('bienes'))
            ->first(fn (array $index) => $index['columns'] === ['qr_codigo'])['name'] ?? null;

        Schema::table('bienes', function (Blueprint $table) use ($indiceQr) {
            if ($indiceQr) {
                $table->dropIndex($indiceQr);
            }
            $table->dropColumn('qr_codigo');
        });

        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 100)->unique();
            $table->json('valor')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Nota: si hay bienes sin código patrimonial, no se puede volver a hacerlo obligatorio;
     * se les asigna el código de barras como código patrimonial antes de revertir.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuraciones');

        DB::table('bienes')->whereNull('codigo_patrimonial')->update(['codigo_patrimonial' => DB::raw('codigo_barras')]);

        Schema::table('bienes', function (Blueprint $table) {
            $table->string('qr_codigo', 255)->nullable()->unique()->after('estado');
        });

        DB::table('bienes')->update(['qr_codigo' => DB::raw("CONCAT('QR-', codigo_patrimonial)")]);

        Schema::table('bienes', function (Blueprint $table) {
            $table->string('codigo_patrimonial', 50)->nullable(false)->change();
            $table->dropIndex('idx_bien_etiqueta_impresa');
            $table->dropColumn('etiqueta_impresa_at');
            $table->dropUnique(['codigo_barras']);
            $table->dropColumn('codigo_barras');
        });
    }

    private function asignarCodigosExistentes(): void
    {
        $sedes = DB::table('sedes')->pluck('codigo', 'id');

        DB::table('bienes')->orderBy('id')->select(['id', 'sede_id'])->each(function (object $bien) use ($sedes) {
            $codigoSede = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($sedes[$bien->sede_id] ?? '')));
            $prefijo = preg_match('/^[A-Z0-9]{1,4}$/', $codigoSede) ? $codigoSede : 'S'.str_pad((string) $bien->sede_id, 2, '0', STR_PAD_LEFT);

            DB::table('bienes')->where('id', $bien->id)->update([
                'codigo_barras' => sprintf('CAT-%s-%06d', $prefijo, $bien->id),
            ]);
        });
    }
};
