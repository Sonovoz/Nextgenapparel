<?php

namespace Database\Seeders;

use App\Models\Caja;
use App\Models\Color;
use App\Models\Familia;
use App\Models\Pack;
use App\Models\Prenda;
use App\Models\ReglaMinimo;
use App\Models\ReglaPrecio;
use App\Models\Talla;
use App\Models\ZonaEnvio;
use App\Models\ZonaImpresion;
use Illuminate\Database\Seeder;

/**
 * Reglas de precio, envíos y un pack DE EJEMPLO (los de la ficha § 6), para
 * probar en Herd con el simulador. Todo se cambia en el backend. Se puede
 * ejecutar varias veces sin duplicar. Necesita EjemplosCatalogoSeeder.
 *
 *   php artisan db:seed --class=EjemplosPreciosSeeder
 */
class EjemplosPreciosSeeder extends Seeder
{
    public function run(): void
    {
        $familia = fn (string $slug) => Familia::where('slug->es', $slug)->value('id');

        ReglaMinimo::updateOrCreate(['nombre->es' => 'Mínimo general (ejemplo)'], [
            'nombre' => ['es' => 'Mínimo general (ejemplo)', 'en' => 'General minimum (example)'],
            'tipo' => 'unidades_total', 'valor' => 15, 'ambito' => 'catalogo', 'activa' => true,
        ]);

        ReglaPrecio::updateOrCreate(['nombre->es' => 'Tramo 50 (ejemplo)'], [
            'nombre' => ['es' => 'Tramo 50 (ejemplo)', 'en' => '50+ tier (example)'],
            'tipo' => 'tramo_cantidad', 'condiciones' => ['minimo_unidades' => 50],
            'efecto' => 'porcentaje', 'valor' => 1000, 'aplica_a' => 'prendas', 'ambito' => 'catalogo', 'activa' => true,
        ]);
        ReglaPrecio::updateOrCreate(['nombre->es' => 'Combinado 15+15 (ejemplo)'], [
            'nombre' => ['es' => 'Combinado 15+15 (ejemplo)', 'en' => '15+15 combo (example)'],
            'tipo' => 'combinada', 'efecto' => 'porcentaje', 'valor' => 1200, 'aplica_a' => 'todo', 'ambito' => 'catalogo', 'activa' => true,
            'condiciones' => ['grupos' => [
                ['ambito' => 'familia', 'ids' => [$familia('camisetas')], 'minimo' => 15],
                ['ambito' => 'familia', 'ids' => [$familia('polos'), $familia('sudaderas')], 'minimo' => 15],
            ]],
        ]);

        $peninsula = ZonaEnvio::updateOrCreate(['nombre->es' => 'Península (ejemplo)'], [
            'nombre' => ['es' => 'Península (ejemplo)', 'en' => 'Mainland Spain (example)'], 'paises' => ['ES'], 'prefijos_cp' => null,
            'metodo' => 'por_kilo', 'precio_kg_extra' => 80, 'gratis_desde' => 50000, 'plazo_dias' => 2, 'activa' => true,
        ]);
        foreach ([2000 => 490, 5000 => 690, 10000 => 990] as $hasta => $precio) {
            $peninsula->tarifas()->updateOrCreate(['hasta_gramos' => $hasta], ['precio' => $precio]);
        }
        ZonaEnvio::updateOrCreate(['nombre->es' => 'Baleares (ejemplo)'], [
            'nombre' => ['es' => 'Baleares (ejemplo)', 'en' => 'Balearic Islands (example)'], 'paises' => ['ES'], 'prefijos_cp' => ['07'],
            'metodo' => 'por_caja', 'precio_caja' => 1200, 'plazo_dias' => 4, 'activa' => true,
        ]);
        Caja::updateOrCreate(['nombre' => 'Caja grande (ejemplo)'], ['capacidad_prendas' => 50, 'peso_max_gramos' => 20000, 'activa' => true]);

        $camiseta = Prenda::where('slug->es', 'camiseta-essential')->first();
        $negro = Color::where('nombre->es', 'Negro')->first();
        $frontal = ZonaImpresion::where('clave', 'frontal')->first();
        if ($camiseta && $negro && $frontal) {
            $tallas = Talla::pluck('id', 'nombre');
            $pack = Pack::withTrashed()->updateOrCreate(['slug->es' => 'pack-joven'], [
                'nombre' => ['es' => 'Pack joven (ejemplo)', 'en' => 'Youth pack (example)'], 'slug' => ['es' => 'pack-joven', 'en' => 'youth-pack'],
                'descripcion' => ['es' => '10 camisetas negras con impresión frontal: 2 L, 5 XL y 3 XXL.', 'en' => '10 black T-shirts with front print: 2 L, 5 XL and 3 XXL.'],
                'precio' => 8182, 'reparto' => 'fijo', 'activo' => true, 'deleted_at' => null,
            ]);
            $pack->lineas()->delete();
            $pack->lineas()->create([
                'prenda_id' => $camiseta->id, 'unidades' => 10, 'colores' => [$negro->id],
                'reparto_tallas' => [$tallas['L'] => 2, $tallas['XL'] => 5, $tallas['XXL'] => 3],
            ]);
            $pack->zonas()->sync([$frontal->id]);
        }
    }
}
