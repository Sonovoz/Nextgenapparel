<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D206: la resolución que declara el PNG (fragmento pHYs), en ppp; null si no la declara. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disenos', function (Blueprint $table) {
            $table->unsignedSmallInteger('ppp_archivo')->nullable()->after('profundidad_bits');
        });
        // El pedido guarda su copia del diseño: también el dato (el diseño puede borrarse después).
        Schema::table('impresiones_pedido', function (Blueprint $table) {
            $table->unsignedSmallInteger('ppp_archivo')->nullable()->after('ppp');
        });
    }

    public function down(): void
    {
        Schema::table('impresiones_pedido', function (Blueprint $table) {
            $table->dropColumn('ppp_archivo');
        });
        Schema::table('disenos', function (Blueprint $table) {
            $table->dropColumn('ppp_archivo');
        });
    }
};
