<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D224: cuántos diseños distintos puede poner el cliente en un pack (1 por defecto, como hasta ahora). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packs', function (Blueprint $tabla): void {
            $tabla->unsignedTinyInteger('max_disenos')->default(1)->after('reparto');
        });
    }

    public function down(): void
    {
        Schema::table('packs', function (Blueprint $tabla): void {
            $tabla->dropColumn('max_disenos');
        });
    }
};
