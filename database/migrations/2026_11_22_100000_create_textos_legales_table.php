<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Textos legales generados con los datos del sistema (paso 8.1, D183), por versiones inmutables. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textos_legales', function (Blueprint $table) {
            $table->id();
            $table->string('pagina', 40);
            $table->unsignedInteger('version');
            $table->longText('contenido'); // Markdown
            $table->json('pendientes')->nullable();
            $table->dateTime('generado_en');
            $table->dateTime('revisado_en')->nullable();
            $table->string('revisado_por', 120)->nullable();
            $table->timestamps();
            $table->unique(['pagina', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textos_legales');
    }
};
