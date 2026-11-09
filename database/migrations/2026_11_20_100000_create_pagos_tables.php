<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pagos con Redsys (fase 7, D181), como en Clave de Alma: un intento por visita a la pasarela y los avisos ya aplicados. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->restrictOnDelete();
            $table->string('pasarela', 20);
            $table->string('metodo', 20)->nullable();
            $table->string('referencia', 120)->unique();
            $table->unsignedInteger('importe');
            $table->string('estado', 20)->index(); // pendiente | cobrado | rechazado
            $table->string('error', 120)->nullable();
            $table->boolean('pruebas')->default(false);
            $table->dateTime('cobrado_en')->nullable();
            $table->timestamps();
        });

        // Cada aviso de la pasarela se aplica una sola vez.
        Schema::create('avisos_pasarela', function (Blueprint $table) {
            $table->id();
            $table->string('pasarela', 20);
            $table->string('evento', 120);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['pasarela', 'evento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avisos_pasarela');
        Schema::dropIfExists('pagos');
    }
};
