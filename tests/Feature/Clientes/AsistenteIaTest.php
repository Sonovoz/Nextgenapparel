<?php

namespace Tests\Feature\Clientes;

use App\Dominio\Asistente\AsistenteVenta;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Pages\AjustesIa;
use App\Models\Ajuste;
use App\Models\Cliente;
use App\Models\Color;
use App\Models\ConsumoIa;
use App\Models\Familia;
use App\Models\Prenda;
use App\Models\Talla;
use App\Models\User;
use App\Models\Variante;
use App\Models\ZonaImpresion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as PeticionHttp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Asistente IA de venta (v2, D194), con OpenAI simulado. El precio sale del motor (P1: 15 camisetas con frontal,
 * 127,05 € con IVA), nunca de la IA.
 */
class AsistenteIaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('ia.openai_key', 'sk-prueba');
        Ajuste::guardar('ia.activa', true);
        $negro = Color::factory()->create(['nombre' => ['es' => 'Negro']]);
        $m = Talla::factory()->create(['nombre' => 'M']);
        $frontal = ZonaImpresion::factory()->create(['nombre' => ['es' => 'Frontal'], 'ancho_max_mm' => 280, 'alto_max_mm' => 380, 'desde_cuello_mm' => 75]);
        $prenda = Prenda::factory()->for(Familia::factory()->create())->create(['nombre' => ['es' => 'Camiseta Essential']]);
        Variante::factory()->for($prenda)->create(['color_id' => $negro->id, 'talla_id' => $m->id, 'precio_base' => 400]);
        $prenda->zonas()->attach($frontal->id, ['precio' => 300, 'coste' => 100]);
        $this->ids = ['prenda' => $prenda->id, 'color' => $negro->id, 'talla' => $m->id, 'zona' => $frontal->id];
        $this->usuario = User::factory()->create(['email_verified_at' => now()]);
        Cliente::factory()->create(['user_id' => $this->usuario->id, 'correo' => $this->usuario->email]);
    }

    /** OpenAI pide primero calcular_precio y luego contesta con lo que le devolvió la herramienta. */
    private function openAiSimulado(): void
    {
        $ids = $this->ids;
        Http::fake(['api.openai.com/*' => function (PeticionHttp $p) use ($ids) {
            $ultimo = collect($p['messages'])->last();
            if ($ultimo['role'] === 'tool') {
                $precio = json_decode($ultimo['content'], true)['total_con_iva_sin_envio'];

                return Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => "Te salen por {$precio} sin envío."]]], 'usage' => ['prompt_tokens' => 900, 'completion_tokens' => 40]]);
            }

            return Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [['id' => 'c1', 'type' => 'function',
                'function' => ['name' => 'calcular_precio', 'arguments' => json_encode(['prenda_id' => $ids['prenda'], 'color_id' => $ids['color'], 'zonas' => [$ids['zona']], 'tallas' => [$ids['talla'] => 15]])]]]]]],
                'usage' => ['prompt_tokens' => 800, 'completion_tokens' => 30]]);
        }]);
    }

    public function test_responde_con_el_precio_del_motor_y_apunta_el_consumo(): void
    {
        $this->openAiSimulado();

        $this->actingAs($this->usuario)->postJson('/es/asistente', ['mensajes' => [['rol' => 'cliente', 'texto' => '¿Cuánto cuestan 15 camisetas negras M con el logo delante?']]])
            ->assertOk()->assertJson(['respuesta' => 'Te salen por 127,05 € sin envío.']);

        $consumo = ConsumoIa::sole();
        $this->assertSame([1700, 70], [$consumo->tokens_entrada, $consumo->tokens_salida]);
        $this->assertSame(intdiv(1700 * 15 + 70 * 60 + 999, 1000), $consumo->coste);
        Http::assertSent(fn (PeticionHttp $p) => $p->hasHeader('Authorization', 'Bearer sk-prueba') && $p['model'] === 'gpt-4o-mini'
            && str_contains($p['messages'][0]['content'], 'NUNCA digas un precio'));
    }

    public function test_si_openai_falla_responde_503_y_se_oculta_un_rato(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['error' => 'caído'], 500)]);
        $cliente = $this->usuario->cliente;

        $this->actingAs($this->usuario)->postJson('/es/asistente', ['mensajes' => [['rol' => 'cliente', 'texto' => 'Hola']]])->assertStatus(503);

        $this->assertTrue(Cache::has('ia.caida'));
        $this->assertFalse(AsistenteVenta::disponible($cliente));
        $this->get('/es')->assertOk()->assertDontSee('id="asistente"', false);
    }

    public function test_limites_sin_cuenta_sin_clave_por_dia_y_del_mes(): void
    {
        $this->postJson('/es/asistente', ['mensajes' => [['rol' => 'cliente', 'texto' => 'Hola']]])->assertUnauthorized();

        $cliente = $this->usuario->cliente;
        $this->assertTrue(AsistenteVenta::disponible($cliente));
        $this->actingAs($this->usuario)->get('/es')->assertSee('id="asistente"', false);

        Ajuste::guardar('ia.mensajes_dia', 1);
        ConsumoIa::create(['cliente_id' => $cliente->id, 'uso' => 'venta', 'tokens_entrada' => 1, 'tokens_salida' => 1, 'coste' => 1]);
        Http::fake();
        $this->actingAs($this->usuario)->postJson('/es/asistente', ['mensajes' => [['rol' => 'cliente', 'texto' => 'Hola']]])->assertJson(['respuesta' => __('asistente.limite_dia')]);
        Http::assertNothingSent();

        ConsumoIa::create(['uso' => 'venta', 'tokens_entrada' => 1, 'tokens_salida' => 1, 'coste' => 20 * 100 * 1000]); // 20 € gastados
        $this->assertFalse(AsistenteVenta::disponible($cliente));

        config()->set('ia.openai_key', null);
        ConsumoIa::query()->delete();
        $this->assertFalse(AsistenteVenta::disponible($cliente));
    }

    public function test_ajustes_ia_para_quien_tiene_ajustes(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::Administrador))->get(AjustesIa::getUrl(panel: 'admin'))->assertOk()->assertSee('Gastado este mes');
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda))->get(AjustesIa::getUrl(panel: 'admin'))->assertForbidden();
    }
}
