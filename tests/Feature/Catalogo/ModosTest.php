<?php

namespace Tests\Feature\Catalogo;

use App\Models\Familia;
use App\Models\Pack;
use App\Models\Prenda;
use App\Models\ZonaImpresion;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
