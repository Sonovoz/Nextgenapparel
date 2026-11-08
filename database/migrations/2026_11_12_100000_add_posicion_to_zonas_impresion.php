<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Dónde está cada zona en la prenda (paso 5.6, D99): su borde de arriba en mm bajo el
| cuello y su centro en mm desde el centro de la prenda (negativo = a la izquierda de
| quien la lleva). Con esto, el diseño colocado en el visor se guarda en mm reales y el
| servidor comprueba que queda dentro de su zona. Se edita en Catálogo → Zonas.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zonas_impresion', function (Blueprint $table) {
            $table->unsignedSmallInteger('desde_cuello_mm')->default(0)->after('alto_max_mm');
            $table->smallInteger('desde_centro_mm')->default(0)->after('desde_cuello_mm');
        });

        // Las colocaciones de partida de D99.
        foreach (['frontal' => [75, 0], 'pecho' => [75, -100], 'espalda' => [100, 0]] as $clave => [$cuello, $centro]) {
            DB::table('zonas_impresion')->where('clave', $clave)->update(['desde_cuello_mm' => $cuello, 'desde_centro_mm' => $centro]);
        }
    }

    public function down(): void
    {
        Schema::table('zonas_impresion', function (Blueprint $table) {
            $table->dropColumn(['desde_cuello_mm', 'desde_centro_mm']);
        });
    }
};
