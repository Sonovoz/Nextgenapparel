<?php

namespace Tests\Feature\Carrito;

use App\Dominio\Catalogo\Cara;
use App\Dominio\Catalogo\TipoFoto;
use App\Models\Carrito;
use App\Models\Color;
use App\Models\Diseno;
use App\Models\Familia;
use App\Models\Foto;
use App\Models\FotoZona;
use App\Models\LineaCarrito;
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
        $this->get('/es/packs/pack-joven')->assertOk()->assertSee('Pack joven')->assertSee('99,00 €')->assertSee('Reparte las 10 prendas entre las tallas');
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

    /** D220: el nombre de la colección viaja con el pack del carrito al pedido; la página trae el lienzo sin «Escribir mi marca». */
    public function test_el_nombre_de_la_coleccion_se_guarda_con_el_pack(): void
    {
        $diseno = $this->diseno(['propietario' => null]);
        $this->get('/es/packs/pack-joven')->assertOk()->assertSee('Nombre de tu marca o colección')->assertSee('Repartir por mí')->assertDontSee('Escribir mi marca');

        $lineaPack = $this->pack->lineas()->sole();
        $this->post('/es/packs/pack-joven', ['origen' => 'diseno', 'diseno' => $diseno->uuid, 'coleccion' => '  La Vaca Lola  ', 'elecciones' => [
            $lineaPack->id => ['color' => (string) $this->negro->id, 'tallas' => [$this->tallas['L'] => '4', $this->tallas['XL'] => '6']],
        ]])->assertRedirect('/es/carrito')->assertSessionHasNoErrors();

        $this->assertSame('La Vaca Lola', Carrito::sole()->lineas()->sole()->coleccion);

        $this->post('/es/packs/pack-joven', ['origen' => 'diseno', 'diseno' => $diseno->uuid, 'coleccion' => str_repeat('a', 61)])->assertSessionHasErrors('coleccion');
    }

    /** D222: la página del pack enseña la prenda con el diseño elegido en cada zona del pack. */
    public function test_la_maqueta_viva_pone_el_diseno_elegido_en_la_zona_del_pack(): void
    {
        $prenda = $this->pack->lineas()->sole()->prenda;
        $foto = Foto::create(['prenda_id' => $prenda->id, 'color_id' => $this->negro->id, 'tipo' => TipoFoto::Maqueta, 'cara' => Cara::Delantera, 'ruta' => 'catalogo/1/negro.webp']);
        FotoZona::create(['foto_id' => $foto->id, 'zona_impresion_id' => $this->frontal->id, 'x' => 3770, 'y' => 2260, 'ancho' => 2460, 'alto' => 2790]);
        $this->get('/es/packs/pack-joven')->assertSee('Elige o sube tu diseño y lo verás aquí');

        $diseno = $this->diseno([]);
        $this->get('/es/packs/pack-joven')->assertOk()->assertSee('Así quedará tu colección')
            ->assertSee('left: 37.7%; top: 22.6%; width: 24.6%; height: 27.9%;', false)
            ->assertSee(route('disenos.miniatura', ['diseno' => $diseno->uuid]), false);
    }

    /** D224: el pack admite hasta N diseños y el cliente elige en qué zonas incluidas va cada uno (o ninguno). */
    public function test_con_dos_disenos_cada_zona_lleva_el_suyo_y_se_pueden_dejar_zonas_sin_imprimir(): void
    {
        $espalda = ZonaImpresion::factory()->create(['nombre' => ['es' => 'Espalda'], 'ancho_max_mm' => 300, 'alto_max_mm' => 400, 'desde_cuello_mm' => 100, 'desde_centro_mm' => 0]);
        $lineaPack = $this->pack->lineas()->sole();
        $lineaPack->prenda->zonas()->attach($espalda->id, ['precio' => 350, 'coste' => 140]);
        $this->pack->zonas()->attach($espalda->id);
        $this->pack->update(['max_disenos' => 2]);
        $uno = $this->diseno([]);
        $dos = Diseno::forceCreate([...$uno->only(['propietario', 'nombre_original', 'ruta', 'miniatura', 'bytes', 'ancho_px', 'alto_px', 'util_x', 'util_y', 'util_ancho_px', 'util_alto_px', 'fondo', 'profundidad_bits', 'derechos_declarados_en', 'derechos_texto']),
            'uuid' => (string) Str::uuid(), 'sha256' => hash('sha256', 'dos')]);
        $reparto = [$lineaPack->id => ['color' => (string) $this->negro->id, 'tallas' => [$this->tallas['L'] => '4', $this->tallas['XL'] => '6']]];

        $this->get('/es/packs/pack-joven')->assertOk()->assertSee('Tus diseños (hasta 2)')->assertSee('Diseño 2')->assertSee('Sin imprimir');

        // Diseño 1 en el frontal y Diseño 2 en la espalda.
        $this->post('/es/packs/pack-joven', ['disenos' => [$uno->uuid, $dos->uuid], 'colocacion' => [$this->frontal->id => '1', $espalda->id => '2'], 'elecciones' => $reparto])
            ->assertRedirect('/es/carrito')->assertSessionHasNoErrors();
        $impresiones = LineaCarrito::sole()->impresiones()->pluck('diseno_id', 'zona_impresion_id')->all();
        $this->assertEquals([$this->frontal->id => $uno->id, $espalda->id => $dos->id], $impresiones);

        // Solo el frontal, aunque la espalda esté incluida.
        $this->post('/es/packs/pack-joven', ['disenos' => [$uno->uuid], 'colocacion' => [$this->frontal->id => '1', $espalda->id => '0'], 'elecciones' => $reparto])->assertSessionHasNoErrors();
        $this->assertSame([$this->frontal->id], LineaCarrito::latest('id')->firstOrFail()->impresiones()->pluck('zona_impresion_id')->all());

        // Ninguna zona, un Diseño 2 sin elegir o más diseños de los permitidos: no se añade.
        $this->post('/es/packs/pack-joven', ['disenos' => [$uno->uuid], 'colocacion' => [$this->frontal->id => '0', $espalda->id => '0'], 'elecciones' => $reparto])->assertSessionHasErrors('colocacion');
        $this->post('/es/packs/pack-joven', ['disenos' => [$uno->uuid], 'colocacion' => [$espalda->id => '2'], 'elecciones' => $reparto])->assertSessionHasErrors('colocacion');
        $this->post('/es/packs/pack-joven', ['disenos' => [$uno->uuid, $dos->uuid, $uno->uuid], 'colocacion' => [$this->frontal->id => '1'], 'elecciones' => $reparto])->assertSessionHasErrors('disenos');
        $this->assertSame(2, LineaCarrito::count());
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

        $this->get('/es/packs/pack-joven')->assertOk()->assertSee('L × 4')->assertDontSee('Repartir por mí');
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
