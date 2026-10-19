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
        Schema::table('bienes', function (Blueprint $table) {
            if (! Schema::hasColumn('bienes', 'tipo_ingreso')) {
                $table->string('tipo_ingreso', 50)->default('con_comprobante')->nullable()->after('estado');
            }
            if (! Schema::hasColumn('bienes', 'numero_comprobante')) {
                $table->string('numero_comprobante', 100)->nullable()->after('tipo_ingreso');
            }
            if (! Schema::hasColumn('bienes', 'comprobante_archivo')) {
                $table->string('comprobante_archivo', 500)->nullable()->after('numero_comprobante');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bienes', function (Blueprint $table) {
            if (Schema::hasColumn('bienes', 'comprobante_archivo')) {
                $table->dropColumn('comprobante_archivo');
            }
            if (Schema::hasColumn('bienes', 'numero_comprobante')) {
                $table->dropColumn('numero_comprobante');
            }
            if (Schema::hasColumn('bienes', 'tipo_ingreso')) {
                $table->dropColumn('tipo_ingreso');
            }
        });
    }
};
