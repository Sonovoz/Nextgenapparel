<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Lo que el cliente eligió al registrarse (tipo, consentimiento de marketing) cuando ya
| había una ficha de invitado con su correo: se aplica al unirla tras verificar el correo
| (revisión N3 de la fase 5, M4), para no perder un consentimiento dado.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('registro_pendiente')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('registro_pendiente');
        });
    }
};
