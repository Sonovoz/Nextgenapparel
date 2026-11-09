<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Facturas (paso 6.5a, D175), portadas de Clave de Alma (ADR 0018): inmutables, con
 * numeración correlativa por serie y año, copia del emisor y del destinatario, desglose
 * de impuestos del pedido y registro Veri*Factu encadenado (desactivado hasta que sea
 * obligatorio). Las rectificativas van en negativo.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Contador de la cadena de facturas: se bloquea su fila para emitir de una en una.
        DB::table('contadores')->insertOrIgnore(['clave' => 'facturas', 'valor' => 0]);

        Schema::create('facturas', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->string('serie', 10);
            $table->unsignedSmallInteger('anio');
            $table->unsignedInteger('numero');
            $table->string('codigo', 30)->unique();
            $table->string('tipo', 2); // F1 | F2 | R1 | R5
            $table->foreignId('pedido_id')->constrained('pedidos')->restrictOnDelete();
            $table->foreignId('rectifica_id')->nullable()->constrained('facturas')->restrictOnDelete();
            $table->string('motivo', 255)->nullable();
            $table->dateTime('emitida_en');
            $table->string('idioma', 5);
            $table->json('emisor');
            $table->json('destinatario');
            $table->integer('base_imponible');
            $table->integer('iva');
            $table->integer('recargo');
            $table->integer('total');
            $table->json('desglose'); // por tipo: tipo_iva, recargo, base, cuota, cuota_recargo
            // Veri*Factu
            $table->boolean('verifactu')->default(false);
            $table->char('huella', 64);
            $table->char('huella_anterior', 64)->nullable();
            $table->string('registro_generado_en', 32);
            $table->string('qr_url', 500);
            $table->string('aeat_estado', 20)->index();
            $table->timestamps();
            $table->unique(['serie', 'anio', 'numero']);
        });

        Schema::create('lineas_factura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factura_id')->constrained('facturas')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden');
            $table->string('descripcion', 255);
            $table->integer('cantidad');
            $table->integer('precio_unitario'); // sin IVA, informativo
            $table->unsignedSmallInteger('tipo_iva')->nullable(); // null: el envío, repartido entre los tipos (D122)
            $table->integer('base');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lineas_factura');
        Schema::dropIfExists('facturas');
        DB::table('contadores')->where('clave', 'facturas')->orWhere('clave', 'like', 'factura:%')->delete();
    }
};
