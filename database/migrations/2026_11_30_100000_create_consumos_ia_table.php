<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Asistente IA (v2, D194): lo que gasta cada respuesta, para los límites por cliente y del mes. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumos_ia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('uso', 20); // venta | seo
            $table->unsignedInteger('tokens_entrada');
            $table->unsignedInteger('tokens_salida');
            $table->unsignedInteger('coste'); // milésimas de céntimo (los tokens son baratos)
            $table->timestamps();
            $table->index(['uso', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumos_ia');
    }
};
