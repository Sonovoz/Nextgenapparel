<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Campañas (v2, D190): nueva compra tras la entrega, reactivación y campañas manuales por segmento. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campanas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->json('asunto'); // es/en
            $table->json('texto');  // es/en
            $table->string('segmento', 20); // todos | con_pedidos | sin_pedidos
            $table->string('cupon', 40)->nullable();
            $table->dateTime('enviada_en')->nullable();
            $table->unsignedInteger('enviados')->default(0);
            $table->timestamps();
        });
        // Cada correo comercial enviado: así nadie recibe dos veces el mismo.
        Schema::create('envios_campana', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20); // nueva_compra | reactivacion | campana
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('pedido_id')->nullable()->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('campana_id')->nullable()->constrained('campanas')->cascadeOnDelete();
            $table->dateTime('enviado_en');
            $table->timestamps();
            $table->index(['tipo', 'cliente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envios_campana');
        Schema::dropIfExists('campanas');
    }
};
