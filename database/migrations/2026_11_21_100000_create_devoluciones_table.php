<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Devoluciones por la pasarela (paso 7.2, D182), como en Clave de Alma: inmutables, una por clave. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devoluciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('pagos')->restrictOnDelete();
            $table->foreignId('pedido_id')->constrained('pedidos')->restrictOnDelete();
            $table->unsignedInteger('importe');
            $table->string('clave', 120)->unique(); // misma clave = nunca se devuelve dos veces
            $table->string('referencia', 120)->nullable();
            $table->string('estado', 20); // hecha | fallida
            $table->string('motivo', 255);
            $table->string('error', 500)->nullable();
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devoluciones');
    }
};
