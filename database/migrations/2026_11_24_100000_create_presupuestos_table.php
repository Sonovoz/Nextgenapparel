<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Presupuestos en PDF desde el carrito (D187): copia de las líneas e importes del motor, con validez. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presupuestos', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->string('numero', 20)->unique(); // PRE-0001
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('idioma', 5);
            $table->json('contenido'); // líneas y totales, tal como los calculó el motor
            $table->json('origen');    // lo que hace falta para volver a ponerlo en el carrito
            $table->unsignedInteger('total');
            $table->date('valido_hasta');
            $table->dateTime('convertido_en')->nullable();
            $table->timestamps();
        });
        DB::table('contadores')->insertOrIgnore(['clave' => 'presupuesto', 'valor' => 0]);
    }

    public function down(): void
    {
        DB::table('contadores')->where('clave', 'presupuesto')->delete();
        Schema::dropIfExists('presupuestos');
    }
};
