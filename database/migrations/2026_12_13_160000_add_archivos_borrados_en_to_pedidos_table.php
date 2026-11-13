<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D230: cuándo se borraron los archivos de impresión de un pedido (90 días después del envío). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $tabla): void {
            $tabla->dateTime('archivos_borrados_en')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $tabla): void {
            $tabla->dropIndex(['archivos_borrados_en']);
            $tabla->dropColumn('archivos_borrados_en');
        });
    }
};
