<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * D257: mangas derecha e izquierda (10 × 10 cm, precio por unidad), en las vistas laterales de la prenda. Se crean
 * sin activar y a 0 €: el equipo les pone precio en Productos → Zonas de impresión, sube las fotos laterales y las
 * activa. En una instalación nueva las crea CatalogoInicialSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('zonas_impresion')->exists()) {
            return;
        }
        foreach ([
            'manga_derecha' => ['Manga derecha', 'Right sleeve', 'derecha', 5],
            'manga_izquierda' => ['Manga izquierda', 'Left sleeve', 'izquierda', 6],
        ] as $clave => [$es, $en, $cara, $orden]) {
            if (DB::table('zonas_impresion')->where('clave', $clave)->exists()) {
                continue;
            }
            DB::table('zonas_impresion')->insert([
                'clave' => $clave, 'nombre' => json_encode(['es' => $es, 'en' => $en], JSON_UNESCAPED_UNICODE), 'cara' => $cara,
                'ancho_max_mm' => 100, 'alto_max_mm' => 100, 'desde_cuello_mm' => 80, 'desde_centro_mm' => 0,
                'precio' => 0, 'coste' => 0, 'orden' => $orden, 'activa' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('zonas_impresion')->whereIn('clave', ['manga_derecha', 'manga_izquierda'])
            ->whereNotExists(fn ($q) => $q->from('prenda_zona')->whereColumn('prenda_zona.zona_impresion_id', 'zonas_impresion.id'))->delete();
    }
};
