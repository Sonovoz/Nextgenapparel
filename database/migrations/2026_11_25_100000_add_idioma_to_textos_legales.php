<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Textos legales también en inglés (D188): cada idioma con sus versiones. Los existentes son en castellano. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('textos_legales', function (Blueprint $table) {
            $table->string('idioma', 5)->default('es')->after('pagina');
        });
        Schema::table('textos_legales', function (Blueprint $table) {
            $table->dropUnique(['pagina', 'version']);
            $table->unique(['pagina', 'idioma', 'version']);
        });
    }

    public function down(): void
    {
        Schema::table('textos_legales', function (Blueprint $table) {
            $table->dropUnique(['pagina', 'idioma', 'version']);
            $table->unique(['pagina', 'version']);
        });
        Schema::table('textos_legales', function (Blueprint $table) {
            $table->dropColumn('idioma');
        });
    }
};
