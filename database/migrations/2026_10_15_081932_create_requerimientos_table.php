<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('requerimientos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('restrict');
            $table->string('tipo_requerimiento', 100);
            $table->text('descripcion');
            $table->text('justificacion');
            $table->text('observaciones')->nullable();
            $table->integer('cantidad')->default(1);
            $table->decimal('costo_estimado', 12, 2)->nullable()->default(0);
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->enum('prioridad', ['baja', 'media', 'alta', 'urgente'])->default('media');
            $table->enum('estado', ['pendiente', 'en_cotizacion', 'aprobado', 'rechazado', 'atendido'])->default('pendiente');
            $table->string('archivo_adjunto', 500)->nullable();
            $table->string('archivo_tipo', 100)->nullable();
            $table->string('archivo_nombre_original', 255)->nullable();
            $table->text('motivo_rechazo')->nullable();
            $table->dateTime('fecha_solicitud');
            $table->timestamps();

            $table->index('codigo', 'idx_requerimiento_codigo');
            $table->index('usuario_id', 'idx_requerimiento_usuario');
            $table->index('estado', 'idx_requerimiento_estado');
            $table->index('fecha_solicitud', 'idx_requerimiento_fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requerimientos');
    }
};
