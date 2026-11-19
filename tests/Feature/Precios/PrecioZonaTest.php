<?php

namespace Tests\Feature\Precios;

use App\Dominio\Catalogo\Cara;
use App\Dominio\Precios\Motor\Cesta;
use App\Dominio\Precios\Motor\LineaCesta;
use App\Dominio\Precios\Motor\MotorPrecios;
use App\Models\Color;
use App\Models\Prenda;
use App\Models\Talla;
use App\Models\Variante;
use App\Models\ZonaImpresion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** D256: el precio de impresión es de la zona y vale igual para todas las prendas que la permiten. */
class PrecioZonaTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_precio_de_la_zona_se_aplica_a_todas_las_prendas_que_la_permiten(): void
    {
        $zona = ZonaImpresion::factory()->create(['clave' => 'pecho', 'cara' => Cara::Delantera, 'precio' => 300]);
        $color = Color::factory()->create();
        $talla = Talla::factory()->create();
        $prendas = Prenda::factory()->count(2)->create()->each(function (Prenda $p) use ($zona, $color, $talla) {
            Variante::factory()->for($p)->create(['color_id' => $color->id, 'talla_id' => $talla->id, 'precio_base' => 500]);
            $p->zonas()->attach($zona->id);
        });
        $impresion = fn (Prenda $p) => app(MotorPrecios::class)->calcular(new Cesta([new LineaCesta($p->id, $color->id, [$talla->id => 1], [$zona->id])]))->lineas[0]->impresion;

        $this->assertSame([300, 300], $prendas->map($impresion)->all());

        $zona->update(['precio' => 450]); // se cambia una vez, en la zona
        $this->assertSame([450, 450], $prendas->map($impresion)->all());
    }

    public function test_las_zonas_de_antes_se_convierten_y_se_crean_las_nuevas_sin_activar(): void
    {
        DB::table('zonas_impresion')->delete();
        $vieja = fn (string $clave, int $ancho, int $alto, string $cara) => ZonaImpresion::factory()->create(['clave' => $clave, 'ancho_max_mm' => $ancho, 'alto_max_mm' => $alto, 'cara' => $cara, 'precio' => 300]);
        $frontal = $vieja('frontal', 280, 380, 'delantera');
        $pecho = $vieja('pecho', 90, 90, 'delantera');
        $espalda = $vieja('espalda', 300, 400, 'trasera');

        (require database_path('migrations/2026_12_15_110000_crear_zonas_de_impresion.php'))->up();

        // Las mismas filas (sus prendas, fotos y pedidos siguen enlazados), con su nombre y medida nuevos.
        $this->assertSame(['pecho', 'Pecho', 260, 300, 300], [$frontal->fresh()->clave, $frontal->fresh()->getTranslation('nombre', 'es'), $frontal->fresh()->ancho_max_mm, $frontal->fresh()->alto_max_mm, $frontal->fresh()->precio]);
        $this->assertSame(['bolsillo', 100, 100], [$pecho->fresh()->clave, $pecho->fresh()->ancho_max_mm, $pecho->fresh()->alto_max_mm]);
        $this->assertSame(['espalda', 300, 350], [$espalda->fresh()->clave, $espalda->fresh()->ancho_max_mm, $espalda->fresh()->alto_max_mm]);
        // Las nuevas, a 0 € y sin activar hasta que el equipo les ponga precio.
        foreach (['espalda_xl' => [350, 420], 'cuello' => [70, 50]] as $clave => [$ancho, $alto]) {
            $nueva = ZonaImpresion::where('clave', $clave)->firstOrFail();
            $this->assertSame([$ancho, $alto, 0, false], [$nueva->ancho_max_mm, $nueva->alto_max_mm, $nueva->precio, $nueva->activa]);
        }
        $this->assertSame(5, ZonaImpresion::count());
    }
}
