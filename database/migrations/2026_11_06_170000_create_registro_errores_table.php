<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registro_errores', function (Blueprint $table) {
            $table->id();
            // Mismo tipo de error en el mismo punto del código = misma huella.
            $table->char('huella', 64)->unique();
            $table->string('nivel', 20)->index();
            $table->string('estado', 20)->default('pendiente')->index();
            $table->string('clase');
            $table->text('mensaje');
            $table->string('archivo', 500)->nullable();
            $table->unsignedInteger('linea')->nullable();
            $table->unsignedInteger('ocurrencias')->default(1);
            $table->dateTime('primera_vez');
            $table->dateTime('ultima_vez')->index();
            $table->string('url', 2000)->nullable();
            $table->string('metodo', 10)->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('traza')->nullable();
            $table->timestamp('alertado_en')->nullable();
            $table->timestamp('resuelto_en')->nullable();
            $table->foreignId('resuelto_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registro_errores');
    }
};
