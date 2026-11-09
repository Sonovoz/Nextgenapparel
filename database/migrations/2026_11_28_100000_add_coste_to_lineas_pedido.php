<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Márgenes (v2, D191): el coste de cada línea al venderla (prendas lisas + impresión), en céntimos sin IVA. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lineas_pedido', function (Blueprint $table) {
            $table->unsignedInteger('coste')->nullable()->after('neto'); // null: pedidos anteriores (se estima con el coste actual)
        });
    }

    public function down(): void
    {
        Schema::table('lineas_pedido', function (Blueprint $table) {
            $table->dropColumn('coste');
        });
    }
};
