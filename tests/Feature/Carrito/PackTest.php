<?php

namespace Tests\Feature\Carrito;

use App\Models\Carrito;
use App\Models\Color;
use App\Models\Diseno;
use App\Models\Familia;
use App\Models\Pack;
use App\Models\Prenda;
use App\Models\Talla;
use App\Models\Variante;
use App\Models\ZonaImpresion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Página de un pack (paso 5.7): elegir diseño o texto, repartir si se puede y añadir. */
class PackTest extends TestCase
{
    use RefreshDatabase;

    private Pack $pack;

    private ZonaImpresion $frontal;

    private Color $negro;

    /** @var array<string, int> */
    private array $tallas = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->negro = Color::factory()->create(['nombre' => ['es' => 'Negro']]);
        foreach (['L', 'XL'] as $n) {
            $this->tallas[$n] = Talla::factory()->create(['nombre' => $n])->id;
        }
        $this->frontal = ZonaImpresion::factory()->create(['nombre' => ['es' => 'Frontal'], 'ancho_max_mm' => 280, 'alto_max_mm' => 380, 'desde_cuello_mm' => 75, 'desde_centro_mm' => 0]);
        $camiseta = Prenda::factory()->for(Familia::factory()->create())->create(['nombre' => ['es' => 'Camiseta']]);
        foreach ($this->tallas as $t) {
            Variante::factory()->for($camiseta)->create(['color_id' => $this->negro->id, 'talla_id' => $t, 'precio_base' => 400]);
        }
        $camiseta->zonas()->attach($this->frontal->id, ['precio' => 300, 'coste' => 100]);
        $this->pack = Pack::create(['nombre' => ['es' => 'Pack joven'], 'slug' => ['es' => 'pack-joven', 'en' => 'young-pack'], 'precio' => 8182, 'reparto' => 'libre']);
        $this->pack->lineas()->create(['prenda_id' => $camiseta->id, 'unidades' => 10, 'colores' => [$this->negro->id], 'tallas_permitidas' => array_values($this->tallas)]);
        $this->pack->zonas()->attach($this->frontal->id);
    }

    public function test_con_reparto_libre_se_reparte_y_el_logo_se_coloca_solo_en_su_zona(): void
    {
        $diseno = $this->diseno(['propietario' => null]);
        $this->get('/es/packs/pack-joven')->assertOk()->assertSee('Pack joven')->assertSee('99,00 €')->assertSee('Reparte las tallas');
        $this->get('/en/packs/young-pack')->assertOk();

        $lineaPack = $this->pack->lineas()->sole();
        $respuesta = $this->post('/es/packs/pack-joven', ['origen' => 'diseno', 'diseno' => $diseno->uuid, 'elecciones' => [
            $lineaPack->id => ['color' => (string) $this->negro->id, 'tallas' => [$this->tallas['L'] => '4', $this->tallas['XL'] => '6']],
        ]]);

        $respuesta->assertRedirect('/es/carrito')->assertSessionHasNoErrors();
        $impresion = Carrito::sole()->lineas()->sole()->impresiones()->sole();
        // Logo de 2:1 en una frontal de 28 × 38 cm: a todo el ancho, centrado y arriba (7,5 cm bajo el cuello).
        $this->assertSame([$diseno->id, 280, 140, 75, 0], [$impresion->diseno_id, $impresion->ancho_mm, $impresion->alto_mm, $impresion->desde_cuello_mm, $impresion->desde_centro_mm]);
    }

    public function test_un_reparto_incompleto_o_un_diseno_ajeno_no_se_anaden(): void
    {
        $lineaPack = $this->pack->lineas()->sole();
        $this->from('/es/packs/pack-joven')->post('/es/packs/pack-joven', ['origen' => 'texto', 'texto' => 'NEXTGEN', 'fuente' => '1', 'tinta' => 'blanco', 'elecciones' => [
            $lineaPack->id => ['color' => (string) $this->negro->id, 'tallas' => [$this->tallas['L'] => '4']],
        ]])->assertRedirect('/es/packs/pack-joven')->assertSessionHasErrors('pack');

        $ajeno = $this->diseno(['propietario' => 'otra-sesion']);
        $this->post('/es/packs/pack-joven', ['origen' => 'diseno', 'diseno' => $ajeno->uuid, 'elecciones' => [
            $lineaPack->id => ['color' => (string) $this->negro->id, 'tallas' => [$this->tallas['L'] => '10']],
        ]])->assertSessionHasErrors('pack');

        $this->assertSame(0, Carrito::query()->has('lineas')->count());
    }

    public function test_con_reparto_fijo_basta_con_el_texto_y_el_pack_retirado_no_se_ve(): void
    {
        $this->pack->update(['reparto' => 'fijo']);
        $this->pack->lineas()->sole()->update(['tallas_permitidas' => null, 'reparto_tallas' => [$this->tallas['L'] => 4, $this->tallas['XL'] => 6]]);

        $this->get('/es/packs/pack-joven')->assertOk()->assertSee('L × 4')->assertDontSee('Reparte las tallas');
        $this->post('/es/packs/pack-joven', ['origen' => 'texto', 'texto' => 'NEXTGEN', 'fuente' => '2', 'tinta' => 'dorado'])->assertRedirect('/es/carrito');
        $this->assertSame('NEXTGEN', Carrito::sole()->lineas()->sole()->impresiones()->sole()->texto);

        $this->pack->update(['activo' => false]);
        $this->get('/es/packs/pack-joven')->assertNotFound();
    }

    private function diseno(array $datos): Diseno
    {
        // El dueño por defecto es la sesión de la prueba (la misma con la que se pide la página).
        $this->get('/es/packs/pack-joven');
        $propietario = array_key_exists('propietario', $datos) && $datos['propietario'] !== null
            ? $datos['propietario'] : hash('sha256', (string) session('disenos.propietario', tap(Str::random(40), fn ($t) => session(['disenos.propietario' => $t]))));

        return Diseno::forceCreate(['uuid' => (string) Str::uuid(), 'propietario' => $propietario, 'nombre_original' => 'logo.png',
            'ruta' => 'x.png', 'miniatura' => 'x.webp', 'bytes' => 1, 'sha256' => hash('sha256', $propietario), 'ancho_px' => 4000, 'alto_px' => 2000,
            'util_x' => 0, 'util_y' => 0, 'util_ancho_px' => 4000, 'util_alto_px' => 2000, 'fondo' => 'transparente', 'profundidad_bits' => 8,
            'derechos_declarados_en' => now(), 'derechos_texto' => 'Declaro…']);
    }
}
