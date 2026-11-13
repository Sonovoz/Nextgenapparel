<?php

namespace Tests\Feature\Catalogo;

use App\Models\Familia;
use App\Models\Pack;
use App\Models\Prenda;
use App\Models\ZonaImpresion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** «Elige tu modo» (D203): cada modo enseña sus packs y, después, los genéricos. */
class ModosTest extends TestCase
{
    use RefreshDatabase;

    private ?Prenda $prenda = null;

    private ?ZonaImpresion $zona = null;

    private function pack(string $nombre, ?array $modos, int $orden = 0): Pack
    {
        $prenda = $this->prenda ??= Prenda::factory()->for(Familia::factory()->create())->create(['activa' => true]);
        $zona = $this->zona ??= ZonaImpresion::factory()->create();
        $pack = Pack::create(['nombre' => ['es' => $nombre], 'slug' => ['es' => str($nombre)->slug()->toString()], 'precio' => 5000, 'reparto' => 'fijo', 'modos' => $modos, 'orden' => $orden]);
        $pack->lineas()->create(['prenda_id' => $prenda->id, 'unidades' => 10, 'colores' => [], 'tallas_permitidas' => []]);
        $pack->zonas()->attach($zona->id);

        return $pack;
    }

    public function test_cada_modo_ensena_sus_packs_y_despues_los_genericos(): void
    {
        $this->pack('Pack generico', null, 0);
        $this->pack('Pack festival', ['eventos'], 1);
        $this->pack('Pack empresa', ['empresas'], 2);

        $this->get('/es/modo/eventos')->assertOk()->assertSee('Tenemos packs que te pueden interesar')
            ->assertSeeInOrder(['Pack festival', 'Pack generico'])->assertDontSee('Pack empresa');
        $this->get('/es/modo/empresas')->assertOk()->assertSeeInOrder(['Pack empresa', 'Pack generico'])->assertDontSee('Pack festival');
        $this->get('/es/modo/creadores')->assertOk()->assertSee('Pack generico')->assertDontSee('Pack festival');
        $this->get('/es/modo/otro')->assertNotFound();

        // La portada enlaza cada modo.
        $this->get('/es')->assertOk()->assertSee(route('modo', ['idioma' => 'es', 'modo' => 'eventos']), false);
    }

    /** D221: en la portada, «Elige tu modo» va dentro de los packs: cada tarjeta lleva sus modos para filtrar y ya no hay sección aparte. */
    public function test_los_modos_filtran_los_packs_de_la_portada(): void
    {
        $this->pack('Pack generico', null, 0);
        $this->pack('Pack festival', ['eventos', 'empresas'], 1);

        $this->get('/es')->assertOk()
            ->assertSeeInOrder(['id="titulo-packs"', '¿Para qué los quieres?', 'data-modo="creadores"', 'data-modo="eventos"', 'data-modo="empresas"', 'Pack generico', 'Pack festival'], false)
            ->assertSee('data-modos=""', false)->assertSee('data-modos="eventos empresas"', false)
            ->assertDontSee('id="titulo-quien"', false);
    }

    public function test_la_tarjeta_del_pack_usa_su_imagen_de_recurso_o_la_subida(): void
    {
        // D217: cinco imágenes de recurso para los packs; una propia subida manda.
        $pack = $this->pack('Pack festival', null);
        $pack->update(['imagen_recurso' => 'pack-abanico']);
        foreach (array_keys(Pack::IMAGENES_RECURSO) as $clave) {
            $this->assertFileExists(public_path('img/packs/'.$clave.'.webp'));
        }

        $this->get('/es/modo/eventos')->assertOk()->assertSee(asset('img/packs/pack-abanico.webp'), false)->assertSee('con-imagen', false);

        $pack->update(['imagen' => 'packs/propia.webp']);
        $this->assertSame(Storage::disk(Pack::DISCO_IMAGEN)->url('packs/propia.webp'), $pack->fresh()->imagenUrl());
        $pack->update(['imagen' => null, 'imagen_recurso' => null]);
        $this->assertNull($pack->fresh()->imagenUrl()); // sin imagen: la foto de su primera prenda
    }

    public function test_el_precio_del_pack_se_muestra_con_iva_o_en_neto_mas_iva(): void
    {
        // D219: precio 50,00 € sin IVA (21 %): 60,50 € con IVA.
        $pack = $this->pack('Pack festival', null);

        $this->get('/es/modo/eventos')->assertOk()->assertSee('60,50')->assertDontSee('+ IVA');

        $pack->update(['precio_neto' => true]);
        $this->get('/es/modo/eventos')->assertOk()->assertSeeInOrder(['50,00', '+ IVA', '60,50', 'IVA incluido']);
        $this->get(route('packs.ver', ['idioma' => 'es', 'pack' => 'pack-festival']))->assertOk()->assertSeeInOrder(['50,00', '+ IVA', '60,50']);
    }
}
