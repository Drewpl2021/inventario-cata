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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50)->unique();
            $table->string('descripcion', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('apellido', 100);
            $table->string('email', 150)->unique();
            $table->string('password', 255);
            $table->foreignId('rol_id')->constrained('roles')->onUpdate('cascade')->onDelete('restrict');
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->timestamps();
        });

        Schema::create('sedes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('codigo', 20)->unique();
            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->timestamps();
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sede_id')->constrained('sedes')->onUpdate('cascade')->onDelete('restrict');
            $table->string('nombre', 150);
            $table->string('codigo', 30)->nullable();
            $table->string('tipo', 80)->nullable();
            $table->string('ubicacion', 255)->nullable();
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->timestamps();

            $table->unique(['sede_id', 'codigo'], 'uk_area_sede_codigo');
        });

        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->string('descripcion', 255)->nullable();
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->timestamps();
        });

        Schema::create('responsables', function (Blueprint $table) {
            $table->id();
            $table->string('nombres', 100);
            $table->string('apellidos', 100);
            $table->string('dni', 20)->nullable()->unique();
            $table->string('cargo', 100)->nullable();
            $table->foreignId('area_id')->nullable()->constrained('areas')->onUpdate('cascade')->onDelete('set null');
            $table->string('telefono', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->timestamps();
        });

        Schema::create('bienes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_patrimonial', 50)->unique();
            $table->string('codigo_interno', 50)->nullable()->unique();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->string('marca', 100)->nullable();
            $table->string('modelo', 100)->nullable();
            $table->string('numero_serie', 100)->nullable();

            $table->foreignId('categoria_id')->constrained('categorias')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('sede_id')->constrained('sedes')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('area_id')->nullable()->constrained('areas')->onUpdate('cascade')->onDelete('set null');
            $table->foreignId('responsable_id')->nullable()->constrained('responsables')->onUpdate('cascade')->onDelete('set null');

            $table->date('fecha_adquisicion')->nullable();
            $table->decimal('valor_adquisicion', 12, 2)->nullable();
            $table->enum('estado', ['nuevo', 'bueno', 'regular', 'malo', 'mantenimiento', 'inoperativo', 'dado_de_baja'])->default('nuevo');

            $table->string('qr_codigo', 255)->nullable()->unique();
            $table->string('fotografia', 255)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('codigo_patrimonial', 'idx_bien_codigo');
            $table->index('sede_id', 'idx_bien_sede');
            $table->index('categoria_id', 'idx_bien_categoria');
            $table->index('estado', 'idx_bien_estado');
            $table->index('responsable_id', 'idx_bien_responsable');
        });

        Schema::create('movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bien_id')->constrained('bienes')->onDelete('restrict');
            $table->foreignId('sede_origen_id')->nullable()->constrained('sedes')->onDelete('set null');
            $table->foreignId('area_origen_id')->nullable()->constrained('areas')->onDelete('set null');
            $table->foreignId('sede_destino_id')->constrained('sedes')->onDelete('restrict');
            $table->foreignId('area_destino_id')->nullable()->constrained('areas')->onDelete('set null');
            $table->foreignId('responsable_anterior_id')->nullable()->constrained('responsables')->onDelete('set null');
            $table->foreignId('responsable_nuevo_id')->nullable()->constrained('responsables')->onDelete('set null');

            $table->enum('tipo', ['traslado', 'asignacion', 'devolucion', 'reubicacion']);
            $table->text('motivo')->nullable();
            $table->dateTime('fecha_movimiento');
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('restrict');
            $table->text('observaciones')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('bien_id', 'idx_movimiento_bien');
            $table->index('fecha_movimiento', 'idx_movimiento_fecha');
        });

        Schema::create('mantenimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bien_id')->constrained('bienes')->onDelete('restrict');
            $table->enum('tipo', ['preventivo', 'correctivo']);
            $table->text('descripcion');
            $table->string('proveedor', 150)->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->decimal('costo', 12, 2)->default(0);
            $table->enum('estado', ['pendiente', 'en_proceso', 'finalizado', 'cancelado'])->default('pendiente');
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('restrict');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('bien_id', 'idx_mantenimiento_bien');
            $table->index('estado', 'idx_mantenimiento_estado');
        });

        Schema::create('bajas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bien_id')->unique()->constrained('bienes')->onDelete('restrict');
            $table->enum('motivo', ['deterioro', 'obsolescencia', 'perdida', 'robo', 'sin_reparacion', 'otro']);
            $table->text('descripcion')->nullable();
            $table->date('fecha_baja');
            $table->string('documento_referencia', 100)->nullable();
            $table->decimal('valor_residual', 12, 2)->default(0);
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('restrict');
            $table->text('observaciones')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bien_id')->constrained('bienes')->onDelete('restrict');
            $table->enum('tipo', ['factura', 'acta', 'garantia', 'informe', 'baja', 'otro']);
            $table->string('nombre', 255);
            $table->string('ruta', 500);
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('restrict');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->onDelete('set null');
            $table->string('accion', 50);
            $table->string('tabla', 100);
            $table->unsignedBigInteger('registro_id')->nullable();
            $table->json('datos_anteriores')->nullable();
            $table->json('datos_nuevos')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('fecha')->useCurrent();

            $table->index('usuario_id', 'idx_auditoria_usuario');
            $table->index('tabla', 'idx_auditoria_tabla');
            $table->index('fecha', 'idx_auditoria_fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auditoria');
        Schema::dropIfExists('documentos');
        Schema::dropIfExists('bajas');
        Schema::dropIfExists('mantenimientos');
        Schema::dropIfExists('movimientos');
        Schema::dropIfExists('bienes');
        Schema::dropIfExists('responsables');
        Schema::dropIfExists('categorias');
        Schema::dropIfExists('areas');
        Schema::dropIfExists('sedes');
        Schema::dropIfExists('usuarios');
        Schema::dropIfExists('roles');
    }
};
