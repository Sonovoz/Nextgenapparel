<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D202: la declaración de derechos ya no se marca al subir cada diseño, sino al aceptar las condiciones
 * de venta al confirmar el pedido (queda en el pedido). Los diseños nuevos la dejan vacía.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disenos', function (Blueprint $table) {
            $table->dateTime('derechos_declarados_en')->nullable()->change();
            $table->string('derechos_texto', 300)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('disenos')->whereNull('derechos_declarados_en')->update(['derechos_declarados_en' => DB::raw('created_at'), 'derechos_texto' => '']);
        Schema::table('disenos', function (Blueprint $table) {
            $table->dateTime('derechos_declarados_en')->nullable(false)->change();
            $table->string('derechos_texto', 300)->nullable(false)->change();
        });
    }
};
