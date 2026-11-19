<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// D253: margen comercial propio de una prenda de proveedor (%, sobre el coste); vacío = el general de Ajustes.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prendas', function (Blueprint $table) {
            $table->unsignedSmallInteger('margen')->nullable()->after('referencia_proveedor');
        });
    }

    public function down(): void
    {
        Schema::table('prendas', function (Blueprint $table) {
            $table->dropColumn('margen');
        });
    }
};
