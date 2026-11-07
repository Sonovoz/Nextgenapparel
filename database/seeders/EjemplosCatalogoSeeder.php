<?php

namespace Database\Seeders;

use App\Dominio\Catalogo\GenerarVariantes;
use App\Models\Color;
use App\Models\Familia;
use App\Models\Prenda;
use App\Models\Proveedor;
use App\Models\Talla;
use App\Models\ZonaImpresion;
use Illuminate\Database\Seeder;

/**
 * Tres prendas de ejemplo con datos inventados, para probar en local:
 *   php artisan db:seed --class=EjemplosCatalogoSeeder
 *
 * Las fotos no se incluyen: se suben desde el backend (pestaña Fotos).
 */
class EjemplosCatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CatalogoInicialSeeder::class);

        $proveedor = Proveedor::firstOrCreate(['nombre' => 'Textiles de Ejemplo S.L.']);
        $colores = Color::whereIn('codigo_hex', ['#F6F5F2', '#1C1C1F'])->pluck('id')->all();
        $tallas = Talla::pluck('id', 'nombre');
        $zonas = ZonaImpresion::pluck('id', 'clave');

        foreach (self::prendas() as $datos) {
            if (Prenda::withTrashed()->where('slug->es', $datos['slug']['es'])->exists()) {
                continue;
            }

            $prenda = Prenda::create([
                'familia_id' => Familia::where('slug->es', $datos['familia'])->value('id'),
                'proveedor_id' => $proveedor->id,
                'referencia_proveedor' => $datos['referencia'],
                'nombre' => $datos['nombre'],
                'slug' => $datos['slug'],
                'descripcion' => $datos['descripcion'],
                'composicion' => $datos['composicion'],
                'gramaje' => $datos['gramaje'],
                'detalles' => $datos['detalles'],
            ]);

            $tallasPrenda = array_map(fn ($t) => $tallas[$t], $datos['tallas']);
            $pesos = [];
            foreach ($datos['tallas'] as $i => $talla) {
                $pesos[$tallas[$talla]] = $datos['peso'] + $i * $datos['peso_por_talla'];
            }
            app(GenerarVariantes::class)($prenda, $colores, $tallasPrenda, $datos['precio'], $datos['coste'], $datos['peso'], 50, $pesos);

            foreach ($datos['zonas'] as $clave => [$precio, $coste]) {
                $prenda->zonas()->attach($zonas[$clave], ['precio' => $precio, 'coste' => $coste]);
            }

            foreach ($datos['medidas'] as $talla => [$largo, $ancho, $manga]) {
                $prenda->medidas()->create(['talla_id' => $tallas[$talla], 'largo_mm' => $largo, 'ancho_mm' => $ancho, 'manga_mm' => $manga]);
            }
        }
    }

    /** @return list<array<string, mixed>> */
    public static function prendas(): array
    {
        return [
            [
                'familia' => 'camisetas', 'referencia' => 'EJ-TS180',
                'nombre' => ['es' => 'Camiseta Essential', 'en' => 'Essential T-shirt'],
                'slug' => ['es' => 'camiseta-essential', 'en' => 'essential-t-shirt'],
                'descripcion' => [
                    'es' => 'La camiseta básica para empezar tu colección: tacto suave, cuello redondo reforzado y un corte recto que sienta bien a todo el mundo.',
                    'en' => 'The basic tee to start your collection: soft feel, reinforced crew neck and a straight cut that suits everyone.',
                ],
                'composicion' => ['es' => '100 % algodón peinado', 'en' => '100% combed cotton'],
                'gramaje' => 180,
                'detalles' => ['es' => 'Corte unisex. Lavar del revés en frío para alargar la vida de la impresión.', 'en' => 'Unisex fit. Wash inside out in cold water to extend the life of the print.'],
                'tallas' => ['XS', 'S', 'M', 'L', 'XL', 'XXL'],
                'precio' => 420, 'coste' => 230, 'peso' => 150, 'peso_por_talla' => 12,
                'zonas' => ['frontal' => [300, 120], 'pecho' => [180, 70], 'espalda' => [350, 140]],
                'medidas' => ['S' => [690, 480, 190], 'M' => [720, 510, 200], 'L' => [740, 540, 210], 'XL' => [760, 570, 220]],
            ],
            [
                'familia' => 'polos', 'referencia' => 'EJ-PL220',
                'nombre' => ['es' => 'Polo Classic', 'en' => 'Classic Polo'],
                'slug' => ['es' => 'polo-classic', 'en' => 'classic-polo'],
                'descripcion' => [
                    'es' => 'Polo de piqué con tres botones y puños de canalé: elegante para uniformes, equipos y marcas que quieren un acabado cuidado.',
                    'en' => 'Piqué polo with three buttons and ribbed cuffs: smart for uniforms, teams and brands that want a refined finish.',
                ],
                'composicion' => ['es' => '100 % algodón piqué', 'en' => '100% cotton piqué'],
                'gramaje' => 220,
                'detalles' => ['es' => 'Cuello y puños de canalé. Aberturas laterales.', 'en' => 'Ribbed collar and cuffs. Side vents.'],
                'tallas' => ['S', 'M', 'L', 'XL', 'XXL'],
                'precio' => 750, 'coste' => 410, 'peso' => 210, 'peso_por_talla' => 15,
                'zonas' => ['pecho' => [180, 70], 'espalda' => [350, 140]],
                'medidas' => ['S' => [700, 500, 210], 'M' => [720, 530, 220], 'L' => [740, 560, 230], 'XL' => [760, 590, 240]],
            ],
            [
                'familia' => 'sudaderas', 'referencia' => 'EJ-SW280',
                'nombre' => ['es' => 'Sudadera Urban', 'en' => 'Urban Sweatshirt'],
                'slug' => ['es' => 'sudadera-urban', 'en' => 'urban-sweatshirt'],
                'descripcion' => [
                    'es' => 'Sudadera de cuello redondo con interior perchado: abriga, mantiene la forma y luce los diseños grandes en el frontal y la espalda.',
                    'en' => 'Crew-neck sweatshirt with brushed inner fleece: warm, keeps its shape and shows off large designs on the front and back.',
                ],
                'composicion' => ['es' => '80 % algodón, 20 % poliéster', 'en' => '80% cotton, 20% polyester'],
                'gramaje' => 280,
                'detalles' => ['es' => 'Puños y cintura de canalé. Hombro caído.', 'en' => 'Ribbed cuffs and waistband. Dropped shoulder.'],
                'tallas' => ['S', 'M', 'L', 'XL', 'XXL', '3XL'],
                'precio' => 1390, 'coste' => 820, 'peso' => 480, 'peso_por_talla' => 30,
                'zonas' => ['frontal' => [320, 130], 'pecho' => [190, 75], 'espalda' => [380, 150]],
                'medidas' => ['S' => [660, 540, 610], 'M' => [690, 570, 620], 'L' => [710, 600, 630], 'XL' => [730, 630, 640]],
            ],
        ];
    }
}
