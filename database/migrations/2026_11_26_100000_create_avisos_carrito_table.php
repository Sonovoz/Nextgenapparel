<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Carritos abandonados (v2, D189): qué aviso de la secuencia se ha enviado a cada carrito. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avisos_carrito', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrito_id')->constrained('carritos')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->unsignedTinyInteger('paso'); // 0, 1, 2…
            $table->string('cupon', 40)->nullable();
            $table->dateTime('enviado_en');
            $table->timestamps();
            $table->unique(['carrito_id', 'paso']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avisos_carrito');
    }
};
