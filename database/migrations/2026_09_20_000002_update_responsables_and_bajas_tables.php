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
        Schema::table('responsables', function (Blueprint $table) {
            if (! Schema::hasColumn('responsables', 'sede_id')) {
                $table->foreignId('sede_id')->nullable()->after('cargo')->constrained('sedes')->onUpdate('cascade')->onDelete('set null');
            }
        });

        Schema::table('bajas', function (Blueprint $table) {
            if (! Schema::hasColumn('bajas', 'fotografia')) {
                $table->string('fotografia', 255)->nullable()->after('documento_referencia');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('responsables', function (Blueprint $table) {
            if (Schema::hasColumn('responsables', 'sede_id')) {
                $table->dropForeign(['sede_id']);
                $table->dropColumn('sede_id');
            }
        });

        Schema::table('bajas', function (Blueprint $table) {
            if (Schema::hasColumn('bajas', 'fotografia')) {
                $table->dropColumn('fotografia');
            }
        });
    }
};
