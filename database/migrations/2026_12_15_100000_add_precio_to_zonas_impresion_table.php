<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D256: el precio de impresión es de la zona y vale igual para todas las prendas que la permiten. Cada zona toma
 * el precio (y el coste) que más se repetía en sus prendas; la prenda solo dice qué zonas permite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zonas_impresion', function (Blueprint $table) {
            $table->unsignedInteger('precio')->default(0)->after('alto_max_mm'); // céntimos por prenda, sin IVA
            $table->unsignedInteger('coste')->default(0)->after('precio');
        });

        foreach (DB::table('zonas_impresion')->pluck('id') as $zona) {
            $cambios = [];
            foreach (['precio', 'coste'] as $campo) {
                $habitual = DB::table('prenda_zona')->where('zona_impresion_id', $zona)
                    ->select($campo, DB::raw('count(*) as veces'))->groupBy($campo)
                    ->orderByDesc('veces')->orderByDesc($campo)->value($campo);
                if ($habitual !== null) {
                    $cambios[$campo] = (int) $habitual;
                }
            }
            if ($cambios !== []) {
                DB::table('zonas_impresion')->where('id', $zona)->update($cambios);
            }
        }

        Schema::table('prenda_zona', function (Blueprint $table) {
            $table->dropColumn(['precio', 'coste']);
        });
    }

    public function down(): void
    {
        Schema::table('prenda_zona', function (Blueprint $table) {
            $table->unsignedInteger('precio')->default(0);
            $table->unsignedInteger('coste')->default(0);
        });
        foreach (DB::table('zonas_impresion')->get(['id', 'precio', 'coste']) as $zona) {
            DB::table('prenda_zona')->where('zona_impresion_id', $zona->id)->update(['precio' => $zona->precio, 'coste' => $zona->coste]);
        }
        Schema::table('zonas_impresion', function (Blueprint $table) {
            $table->dropColumn(['precio', 'coste']);
        });
    }
};
