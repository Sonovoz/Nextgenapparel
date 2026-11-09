<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* El cliente pide el borrado de su cuenta y lo ejecuta el equipo (paso 5.3). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dateTime('borrado_solicitado_en')->nullable()->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('borrado_solicitado_en');
        });
    }
};
