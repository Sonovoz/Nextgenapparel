<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D217: imagen de cada pack elegida entre las de recurso (public/img/packs). La subida va en «imagen»,
 * que ya existía. Sin ninguna, la tarjeta usa la foto de su primera prenda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packs', function (Blueprint $tabla): void {
            $tabla->string('imagen_recurso', 60)->nullable()->after('imagen');
        });
    }

    public function down(): void
    {
        Schema::table('packs', function (Blueprint $tabla): void {
            $tabla->dropColumn('imagen_recurso');
        });
    }
};
