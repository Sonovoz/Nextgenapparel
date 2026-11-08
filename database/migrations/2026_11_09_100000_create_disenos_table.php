<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Diseños de los clientes (ficha § 5 y § 7, fase 4). El archivo original se
| guarda en el disco privado «disenos» y nunca se sirve directamente.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disenos', function (Blueprint $table) {
            $table->id();
            // Identificador público, imposible de adivinar. char(36) y no el tipo uuid nativo:
            // así la columna es igual en MariaDB 11 (servidor) y 10.4 (XAMPP) y los volcados se restauran en ambos.
            $table->char('uuid', 36)->unique();
            // Dueño: el cliente (la tabla clientes llega en la fase 5, con su clave foránea)
            // o, mientras no tenga cuenta, el hash del token de su sesión.
            $table->unsignedBigInteger('cliente_id')->nullable()->index();
            $table->char('propietario', 64)->nullable();
            $table->string('nombre_original', 150);
            $table->string('ruta', 120);
            $table->string('miniatura', 120)->nullable();
            $table->unsignedInteger('bytes');
            $table->char('sha256', 64);
            $table->unsignedSmallInteger('ancho_px');
            $table->unsignedSmallInteger('alto_px');
            // Lo que se imprime: el diseño sin sus márgenes transparentes (D114).
            $table->unsignedSmallInteger('util_x');
            $table->unsignedSmallInteger('util_y');
            $table->unsignedSmallInteger('util_ancho_px');
            $table->unsignedSmallInteger('util_alto_px');
            $table->string('fondo', 20); // transparente | fondo | fondo_blanco
            $table->string('perfil_color', 80)->nullable();
            $table->unsignedTinyInteger('profundidad_bits');
            $table->dateTime('derechos_declarados_en');
            $table->string('derechos_texto', 300); // la declaración exacta que aceptó, en su idioma
            $table->string('estado', 20)->default('subido'); // subido | aprobado | requiere_cambios | rechazado
            $table->timestamps();
            // El mismo archivo del mismo dueño, una sola vez (también con dos subidas a la vez).
            $table->unique(['propietario', 'sha256']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disenos');
    }
};
