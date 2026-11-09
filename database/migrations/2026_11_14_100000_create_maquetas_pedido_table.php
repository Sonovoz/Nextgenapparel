<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maquetas del pedido (paso 6.2, D98): una imagen por cara de cada línea, generada en el
 * servidor. Y el recorte de lo que se imprime de cada logo (D114), copiado al confirmar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('impresiones_pedido', function (Blueprint $table) {
            $table->unsignedSmallInteger('util_x')->nullable()->after('sha256');
            $table->unsignedSmallInteger('util_y')->nullable()->after('util_x');
        });

        Schema::create('maquetas_pedido', function (Blueprint $table) {
            $table->id();
            $table->foreignId('linea_pedido_id')->constrained('lineas_pedido')->cascadeOnDelete();
            $table->string('cara', 10);
            $table->string('ruta', 160);
            $table->unsignedSmallInteger('ancho_px');
            $table->unsignedSmallInteger('alto_px');
            $table->dateTime('generada_en');
            $table->timestamps();
            $table->unique(['linea_pedido_id', 'cara']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maquetas_pedido');
        Schema::table('impresiones_pedido', fn (Blueprint $table) => $table->dropColumn(['util_x', 'util_y']));
    }
};
