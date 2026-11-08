<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Correo nuevo pendiente de confirmar (revisión N3 del paso 5.3, A1): el correo de la cuenta
| y el de la ficha no cambian hasta que se abre el enlace que llega al correo nuevo.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('correo_pendiente', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('correo_pendiente');
        });
    }
};
