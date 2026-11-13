<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D219: cada pack elige cómo se muestra su precio: IVA incluido (como hasta ahora) o neto + IVA. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packs', function (Blueprint $tabla): void {
            $tabla->boolean('precio_neto')->default(false)->after('precio');
        });
    }

    public function down(): void
    {
        Schema::table('packs', function (Blueprint $tabla): void {
            $tabla->dropColumn('precio_neto');
        });
    }
};
