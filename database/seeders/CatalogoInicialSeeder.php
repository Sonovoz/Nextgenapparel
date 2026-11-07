<?php

namespace Database\Seeders;

use App\Dominio\Catalogo\Cara;
use App\Models\Color;
use App\Models\Familia;
use App\Models\Talla;
use App\Models\ZonaImpresion;
use Illuminate\Database\Seeder;

/**
 * Datos de partida para probar el catálogo en local:
 *   php artisan db:seed --class=CatalogoInicialSeeder
 *
 * Todo se puede cambiar después en el backend. Las medidas de las zonas son
 * de ejemplo hasta que el cliente dé las reales.
 */
class CatalogoInicialSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Camisetas', 'T-shirts', 'camisetas', 't-shirts', 2],
            ['Polos', 'Polo shirts', 'polos', 'polo-shirts', 3],
            ['Sudaderas', 'Sweatshirts', 'sudaderas', 'sweatshirts', 3],
        ] as $orden => [$es, $en, $slugEs, $slugEn, $plazo]) {
            Familia::firstOrCreate(['slug->es' => $slugEs], [
                'nombre' => ['es' => $es, 'en' => $en],
                'slug' => ['es' => $slugEs, 'en' => $slugEn],
                'plazo_base_dias' => $plazo,
                'orden' => $orden,
            ]);
        }

        foreach (['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL'] as $orden => $talla) {
            Talla::firstOrCreate(['nombre' => $talla], ['orden' => $orden]);
        }

        foreach ([['Blanco', 'White', '#F6F5F2'], ['Negro', 'Black', '#1C1C1F']] as $orden => [$es, $en, $hex]) {
            Color::firstOrCreate(['codigo_hex' => $hex], ['nombre' => ['es' => $es, 'en' => $en], 'orden' => $orden]);
        }

        foreach ([
            ['frontal', 'Frontal', 'Front', Cara::Delantera, 280, 380],
            ['pecho', 'Pecho', 'Chest', Cara::Delantera, 90, 90],
            ['espalda', 'Espalda grande', 'Large back', Cara::Trasera, 300, 400],
        ] as $orden => [$clave, $es, $en, $cara, $ancho, $alto]) {
            ZonaImpresion::firstOrCreate(['clave' => $clave], [
                'nombre' => ['es' => $es, 'en' => $en], 'cara' => $cara,
                'ancho_max_mm' => $ancho, 'alto_max_mm' => $alto, 'orden' => $orden,
            ]);
        }
    }
}
