<?php

namespace Tests\Feature\Precios;

use App\Dominio\Asistente\AsistenteEnvios;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Resources\Precios\Pages\ManageZonaEnvio;
use App\Models\ConsumoIa;
use App\Models\Transportista;
use App\Models\ZonaEnvio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** D228: la IA pasa a limpio la tarifa del transportista; PHP comprueba y convierte; se crea al aprobar. */
class AsistenteEnviosTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<string, mixed>  $propuesta */
    private function respuesta(array $propuesta): void
    {
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => json_encode($propuesta)]]],
            'usage' => ['prompt_tokens' => 900, 'completion_tokens' => 300],
        ])]);
    }

    public function test_lee_la_tarifa_y_convierte_kilos_y_euros_descartando_lo_que_no_vale(): void
    {
        config()->set('ia.openai_key', 'sk-prueba');
        $this->respuesta(['zonas' => [
            ['nombre' => 'Península', 'paises' => ['es'], 'prefijos_cp' => [], 'metodo' => 'por_kilo', 'plazo_dias' => 1,
                'tramos' => [['hasta_kg' => 5, 'precio' => '7,50'], ['hasta_kg' => 1, 'precio' => 5.9], ['hasta_kg' => null, 'precio' => 3]], 'precio_kg_extra' => 0.6],
            ['nombre' => 'Baleares', 'paises' => ['ES'], 'prefijos_cp' => ['07'], 'metodo' => 'por_caja', 'plazo_dias' => null, 'precio_caja' => 18],
            ['nombre' => 'Marte', 'paises' => ['Planeta rojo']],
        ], 'preguntas' => ['¿Los precios llevan IVA?']]);

        $p = app(AsistenteEnvios::class)->leer('Península: hasta 1 kg 5,90 €; hasta 5 kg 7,50 €; kilo adicional 0,60 €. Baleares: 18 € por caja.', 'data:image/png;base64,AAAA');

        $this->assertSame([['hasta_gramos' => 1000, 'precio' => 590], ['hasta_gramos' => 5000, 'precio' => 750]], $p['zonas'][0]['tramos']);
        $this->assertSame([['ES'], 60], [$p['zonas'][0]['paises'], $p['zonas'][0]['precio_kg_extra']]);
        $this->assertSame(['07', 'por_caja', 1800, null], [$p['zonas'][1]['prefijos_cp'][0], $p['zonas'][1]['metodo'], $p['zonas'][1]['precio_caja'], $p['zonas'][1]['plazo_dias']]);
        $this->assertCount(2, $p['zonas']); // Marte no tiene un país válido
        $this->assertSame(['¿Los precios llevan IVA?'], $p['preguntas']);
        $this->assertCount(2, $p['descartes']); // el tramo sin kilos y Marte
        $this->assertSame(1, ConsumoIa::where('uso', 'envios')->count());
        // La foto se manda a OpenAI con el texto.
        Http::assertSent(fn ($r) => str_contains(json_encode($r->data()), 'data:image\/png;base64,AAAA'));
    }

    public function test_la_vista_previa_calcula_con_el_motor_avisa_y_crea_al_aprobar(): void
    {
        $gls = Transportista::create(['nombre' => 'GLS']);
        ZonaEnvio::create(['nombre' => ['es' => 'Península antigua'], 'paises' => ['ES'], 'metodo' => 'por_kilo', 'plazo_dias' => 2, 'activa' => true]);
        $propuesta = ['zonas' => [['nombre' => 'Península', 'paises' => ['ES'], 'prefijos_cp' => [], 'metodo' => 'por_kilo', 'plazo_dias' => 1,
            'tramos' => [['hasta_kg' => 1, 'precio' => 5.9], ['hasta_kg' => 5, 'precio' => 7.5]], 'precio_kg_extra' => null]], 'preguntas' => []];

        $vista = (string) ManageZonaEnvio::vistaPrevia($propuesta, $gls->id);
        $this->assertStringContainsString('1 kg → 5,90 €', $vista);
        $this->assertStringContainsString('10 kg → no se puede enviar', $vista); // sin kilo extra
        $this->assertStringContainsString('añade un tramo mayor', $vista);
        $this->assertStringContainsString('Ya hay una zona activa para ese destino: «Península antigua»', $vista);

        $this->assertSame(1, AsistenteEnvios::crear($propuesta, $gls->id, activar: false));
        $zona = ZonaEnvio::with('tarifas')->where('nombre->es', 'Península')->sole();
        $this->assertSame([$gls->id, false, 1], [$zona->transportista_id, $zona->activa, $zona->plazo_dias]);
        $this->assertSame([1000 => 590, 5000 => 750], $zona->tarifas->pluck('precio', 'hasta_gramos')->all());
    }

    public function test_el_boton_solo_sale_con_la_ia_disponible(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::Superadministrador));
        config()->set('ia.openai_key', null);
        Livewire::test(ManageZonaEnvio::class)->assertActionHidden('ia');
        config()->set('ia.openai_key', 'sk-prueba');
        Livewire::test(ManageZonaEnvio::class)->assertActionVisible('ia')->mountAction('ia')->assertActionMounted('ia')->assertHasNoErrors();
    }
}
