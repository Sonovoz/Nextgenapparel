<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Incidencias del cliente con fotos y su resolución (paso 6.6b, D178): reimpresión, reembolso
 * parcial (con su rectificativa parcial) o rechazo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->boolean('parcial')->default(false)->after('rectifica_id'); // rectificativa de un reembolso parcial
        });

        Schema::create('incidencias', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->string('codigo', 20)->unique(); // INC-0001
            $table->foreignId('pedido_id')->constrained('pedidos')->restrictOnDelete();
            $table->string('motivo', 30);
            $table->text('descripcion');
            $table->string('estado', 20)->default('abierta')->index(); // abierta | resuelta
            $table->string('resolucion', 20)->nullable(); // reimpresion | reembolso | rechazo
            $table->unsignedInteger('importe_reembolso')->nullable(); // céntimos, con impuestos
            $table->foreignId('factura_id')->nullable()->constrained('facturas')->restrictOnDelete();
            $table->text('respuesta')->nullable(); // lo que lee el cliente
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resuelta_en')->nullable();
            $table->timestamps();
        });

        Schema::create('fotos_incidencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incidencia_id')->constrained('incidencias')->cascadeOnDelete();
            $table->string('ruta', 160);
            $table->unsignedSmallInteger('ancho_px');
            $table->unsignedSmallInteger('alto_px');
            $table->unsignedInteger('bytes');
            $table->timestamps();
        });

        // La fila del contador, para sumar con un UPDATE (como los pedidos, D170).
        DB::table('contadores')->insertOrIgnore(['clave' => 'incidencia', 'valor' => 0]);
    }

    public function down(): void
    {
        DB::table('contadores')->where('clave', 'incidencia')->delete();
        Schema::dropIfExists('fotos_incidencia');
        Schema::dropIfExists('incidencias');
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn('parcial');
        });
    }
};
