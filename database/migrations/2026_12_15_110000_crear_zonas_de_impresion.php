<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * D256: las zonas de impresión con las que se vende (petición del cliente). Las de antes se convierten, así sus
 * prendas, fotos y pedidos siguen enlazados: la pequeña del pecho pasa a «Bolsillo», la frontal a «Pecho» y la
 * espalda grande a «Espalda». Las nuevas (Espalda XL y Cuello interno) se crean sin activar y a 0 €: el equipo
 * les pone precio en Productos → Zonas de impresión y las activa. Las mangas llegan con la vista lateral.
 */
return new class extends Migration
{
    /** clave => [es, en, cara, ancho mm, alto mm, desde el cuello mm, desde el centro mm, orden] */
    public const ZONAS = [
        'bolsillo' => ['Bolsillo', 'Pocket', 'delantera', 100, 100, 75, -100, 1],
        'pecho' => ['Pecho', 'Chest', 'delantera', 260, 300, 75, 0, 0],
        'espalda' => ['Espalda', 'Back', 'trasera', 300, 350, 100, 0, 2],
        'espalda_xl' => ['Espalda XL', 'Back XL', 'trasera', 350, 420, 100, 0, 3],
        'cuello' => ['Cuello interno (etiqueta)', 'Inside neck (label)', 'delantera', 70, 50, 15, 0, 4],
    ];

    public function up(): void
    {
        if (! DB::table('zonas_impresion')->exists()) {
            return; // instalación nueva: las crea CatalogoInicialSeeder
        }
        $existe = fn (string $clave) => DB::table('zonas_impresion')->where('clave', $clave)->exists();
        // Primero la del pecho pequeña (deja libre la clave «pecho») y después la frontal.
        if ($existe('pecho') && ! $existe('bolsillo') && (int) DB::table('zonas_impresion')->where('clave', 'pecho')->value('ancho_max_mm') <= 150) {
            DB::table('zonas_impresion')->where('clave', 'pecho')->update(['clave' => 'bolsillo']);
        }
        if ($existe('frontal') && ! $existe('pecho')) {
            DB::table('zonas_impresion')->where('clave', 'frontal')->update(['clave' => 'pecho']);
        }

        foreach (self::ZONAS as $clave => [$es, $en, $cara, $ancho, $alto, $cuello, $centro, $orden]) {
            $datos = ['nombre' => json_encode(['es' => $es, 'en' => $en], JSON_UNESCAPED_UNICODE), 'cara' => $cara,
                'ancho_max_mm' => $ancho, 'alto_max_mm' => $alto, 'desde_cuello_mm' => $cuello, 'desde_centro_mm' => $centro,
                'orden' => $orden, 'updated_at' => now()];
            if ($existe($clave)) {
                DB::table('zonas_impresion')->where('clave', $clave)->update($datos);
            } else {
                DB::table('zonas_impresion')->insert($datos + ['clave' => $clave, 'precio' => 0, 'coste' => 0, 'activa' => false, 'created_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // Los nombres y medidas de cada zona se cambian en el panel; no se deshace la conversión.
    }
};
