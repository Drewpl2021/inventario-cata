<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Vincula cada responsable con su cuenta de usuario (`responsables.usuario_id`).
 * - Agrega soft deletes a los registros con historial: bienes, responsables,
 *   sedes, áreas y categorías. Nunca se borran físicamente.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tablasConSoftDeletes = ['bienes', 'responsables', 'sedes', 'areas', 'categorias'];

    public function up(): void
    {
        Schema::table('responsables', function (Blueprint $table) {
            $table->foreignId('usuario_id')->nullable()->unique()->after('id')
                ->constrained('usuarios')->cascadeOnUpdate()->nullOnDelete();
        });

        foreach ($this->tablasConSoftDeletes as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tablasConSoftDeletes as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        Schema::table('responsables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('usuario_id');
        });
    }
};
