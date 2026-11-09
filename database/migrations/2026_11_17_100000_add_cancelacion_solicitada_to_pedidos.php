<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Paso 6.6: el cliente pide cancelar un pedido ya pagado y el equipo lo resuelve (D177). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->timestamp('cancelacion_solicitada_en')->nullable()->after('numero_seguimiento');
            $table->string('motivo_cancelacion', 500)->nullable()->after('cancelacion_solicitada_en');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn(['cancelacion_solicitada_en', 'motivo_cancelacion']);
        });
    }
};
