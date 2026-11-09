<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cierre de la fase 6 (revisión del instructor, A1 y M1): la base de datos impide lo que el código ya evita.
 * - Una sola factura (F1/F2) por pedido: `pedido_original_id` solo se rellena en ellas y es único.
 * - Una sola factura detrás de cada huella: `huella_anterior` es única (la primera es nula).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->foreignId('pedido_original_id')->nullable()->after('pedido_id')->constrained('pedidos')->restrictOnDelete();
        });
        DB::table('facturas')->whereIn('tipo', ['F1', 'F2'])->update(['pedido_original_id' => DB::raw('pedido_id')]);
        Schema::table('facturas', function (Blueprint $table) {
            $table->unique('pedido_original_id');
            $table->unique('huella_anterior');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            // Primero la clave ajena: MariaDB no deja quitar un índice que la sostiene.
            $table->dropForeign(['pedido_original_id']);
            $table->dropUnique(['huella_anterior']);
            $table->dropUnique(['pedido_original_id']);
            $table->dropColumn('pedido_original_id');
        });
    }
};
