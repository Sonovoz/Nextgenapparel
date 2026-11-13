<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D220: nombre que el cliente da a su colección (su marca) al añadir un pack; pasa del carrito al pedido. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['lineas_carrito', 'lineas_pedido'] as $tabla) {
            Schema::table($tabla, function (Blueprint $t): void {
                $t->string('coleccion', 60)->nullable()->after('tipo');
            });
        }
    }

    public function down(): void
    {
        foreach (['lineas_carrito', 'lineas_pedido'] as $tabla) {
            Schema::table($tabla, function (Blueprint $t): void {
                $t->dropColumn('coleccion');
            });
        }
    }
};
