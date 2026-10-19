<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bajas', function (Blueprint $table) {
            if (! Schema::hasColumn('bajas', 'estado')) {
                $table->enum('estado', ['solicitada', 'aprobada', 'rechazada'])->default('aprobada')->after('motivo');
            }
            if (! Schema::hasColumn('bajas', 'motivo_rechazo')) {
                $table->text('motivo_rechazo')->nullable()->after('observaciones');
            }
        });

        // Set all existing bajas to aprobada
        DB::table('bajas')->update(['estado' => 'aprobada']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bajas', function (Blueprint $table) {
            if (Schema::hasColumn('bajas', 'estado')) {
                $table->dropColumn('estado');
            }
            if (! Schema::hasColumn('bajas', 'motivo_rechazo')) {
                $table->dropColumn('motivo_rechazo');
            }
        });
    }
};
