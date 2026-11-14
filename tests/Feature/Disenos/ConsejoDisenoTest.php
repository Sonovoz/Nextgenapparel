<?php

namespace Tests\Feature\Disenos;

use App\Dominio\Asistente\ConsejoDiseno;
use App\Dominio\Catalogo\Cara;
use App\Dominio\Disenos\GuardarDiseno;
use App\Models\Ajuste;
use App\Models\ConsumoIa;
use App\Models\Diseno;
use App\Models\Foto;
use App\Models\FotoZona;
use App\Models\Prenda;
use App\Models\ZonaImpresion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as Peticion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PngDePrueba;
use Tests\TestCase;

/** D246: la IA mira el diseño y, con las medidas de la tienda, recomienda zona y redacta el aviso de calidad. */
class ConsejoDisenoTest extends TestCase
{
    use RefreshDatabase;

    private Prenda $prenda;

    private ZonaImpresion $pecho;

    private ZonaImpresion $frontal;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(GuardarDiseno::DISCO);
        Storage::fake('public');
        Cache::flush();
        config()->set('ia.openai_key', 'sk-prueba');
        $this->prenda = Prenda::factory()->create();
        $this->pecho = ZonaImpresion::factory()->create(['nombre' => ['es' => 'Pecho', 'en' => 'Chest'], 'ancho_max_mm' => 90, 'alto_max_mm' => 90]);
        $this->frontal = ZonaImpresion::factory()->create(['nombre' => ['es' => 'Frontal', 'en' => 'Front'], 'ancho_max_mm' => 280, 'alto_max_mm' => 380]);
        $foto = Foto::factory()->create(['prenda_id' => $this->prenda->id, 'cara' => Cara::Delantera]);
        FotoZona::create(['foto_id' => $foto->id, 'zona_impresion_id' => $this->frontal->id, 'x' => 3000, 'y' => 2500, 'ancho' => 4000, 'alto' => 5000]);
        FotoZona::create(['foto_id' => $foto->id, 'zona_impresion_id' => $this->pecho->id, 'x' => 5500, 'y' => 2600, 'ancho' => 1500, 'alto' => 1250]);
    }

    /** Un logo apaisado de 1000 × 300 px. */
    private function subir(): Diseno
    {
        $this->postJson('/es/disenos', ['archivo' => new UploadedFile(PngDePrueba::crear(1000, 300, 'transparent', [0, 0, 1000, 300]), 'logo.png', 'image/png', null, true), 'derechos' => '1'])->assertCreated();

        return Diseno::sole();
    }

    private function respuestaIa(array $contenido): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => json_encode($contenido, JSON_UNESCAPED_UNICODE)]]], 'usage' => ['prompt_tokens' => 900, 'completion_tokens' => 120]])]);
    }

    public function test_la_tienda_calcula_las_medidas_de_cada_zona_en_cm(): void
    {
        $medidas = collect(ConsejoDiseno::medidas($this->subir(), $this->prenda, 'es'))->keyBy('id');

        $this->assertSame(['9 × 9 cm', '9 × 2,7 cm', '8,5 × 2,5 cm'], [$medidas[$this->pecho->id]['maximo'], $medidas[$this->pecho->id]['llena'], $medidas[$this->pecho->id]['nitido']]);
        $this->assertSame(['28 × 8,4 cm', 'baja'], [$medidas[$this->frontal->id]['llena'], $medidas[$this->frontal->id]['calidad']]);
    }

    public function test_recomienda_zona_y_descarta_los_textos_con_cifras_inventadas(): void
    {
        $diseno = $this->subir();
        $this->respuestaIa([
            'zona' => $this->pecho->id,
            'motivo' => 'Tu logo apaisado luce mejor en el pecho.',
            'consejos' => [
                $this->pecho->id => 'Tu logo sale nítido hasta 8,5 × 2,5 cm: déjalo así o un poco más pequeño.',
                $this->frontal->id => 'A 28 cm se vería borroso: hazlo de 99 cm en tu programa.', // 99 no lo ha dado la tienda
            ],
        ]);
        $url = route('disenos.consejo', ['idioma' => 'es', 'diseno' => $diseno->uuid]);

        $this->postJson($url, ['prenda' => $this->prenda->id])->assertOk()
            ->assertJson(['zona' => $this->pecho->id, 'zona_nombre' => 'Pecho', 'motivo' => 'Tu logo apaisado luce mejor en el pecho.'])
            ->assertJsonPath('consejos.'.$this->pecho->id, 'Tu logo sale nítido hasta 8,5 × 2,5 cm: déjalo así o un poco más pequeño.')
            ->assertJsonMissingPath('consejos.'.$this->frontal->id);

        // Se envía la imagen del diseño y las medidas de la tienda; nada del cliente.
        Http::assertSent(fn (Peticion $p) => str_contains(json_encode($p->data()), 'data:image\/webp;base64') && str_contains(json_encode($p->data(), JSON_UNESCAPED_UNICODE), '8,5 × 2,5 cm'));

        // El mismo diseño en la misma prenda no vuelve a preguntar a la IA.
        $this->postJson($url, ['prenda' => $this->prenda->id])->assertOk();
        Http::assertSentCount(1);
        $this->assertSame(1, ConsumoIa::where('uso', 'consejo_diseno')->count());
    }

    public function test_sin_ia_o_desactivado_no_hay_consejo_y_el_disenador_sigue_igual(): void
    {
        $diseno = $this->subir();
        $url = route('disenos.consejo', ['idioma' => 'es', 'diseno' => $diseno->uuid]);
        Http::fake();

        Ajuste::guardar('ia.consejo_disenos', false);
        $this->postJson($url, ['prenda' => $this->prenda->id])->assertNoContent();

        Ajuste::guardar('ia.consejo_disenos', true);
        config()->set('ia.openai_key', '');
        $this->postJson($url, ['prenda' => $this->prenda->id])->assertNoContent();
        Http::assertNothingSent();
    }

    public function test_solo_el_dueno_del_diseno_pide_consejo(): void
    {
        $diseno = $this->subir();
        $this->flushSession();

        $this->postJson(route('disenos.consejo', ['idioma' => 'es', 'diseno' => $diseno->uuid]), ['prenda' => $this->prenda->id])->assertNotFound();
    }
}
