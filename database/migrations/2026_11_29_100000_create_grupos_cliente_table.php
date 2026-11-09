<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tarifas por grupo de cliente (v2, D192): un descuento % por grupo, asignado en la ficha del cliente. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupos_cliente', function (Blueprint $table) {
            $table->id();
            $table->json('nombre');
            $table->unsignedSmallInteger('descuento'); // centésimas de %: 1000 = 10 %
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::table('clientes', function (Blueprint $table) {
            $table->foreignId('grupo_cliente_id')->nullable()->constrained('grupos_cliente')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grupo_cliente_id');
        });
        Schema::dropIfExists('grupos_cliente');
    }
};
