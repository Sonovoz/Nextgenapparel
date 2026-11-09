<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Notas del pedido como en WooCommerce (paso 6.3): privadas (solo el equipo) o para el cliente. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas_pedido', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->string('tipo', 10); // privada | cliente
            $table->string('texto', 500);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas_pedido');
    }
};
