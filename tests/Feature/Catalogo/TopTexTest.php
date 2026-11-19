<?php

namespace Tests\Feature\Catalogo;

use App\Dominio\Catalogo\Proveedores\ClienteTopTex;
use App\Dominio\Catalogo\Proveedores\ProveedorNoDisponible;
use App\Dominio\Precios\PrecioConMargen;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Pages\AjustesProveedores;
use App\Models\Ajuste;
use App\Models\Prenda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** D253: conexión con la API de TopTex (token en caché, cabeceras) y precio de venta = coste + margen, a 0,05 €. */
class TopTexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('toptex', ['url' => 'https://api.toptex.io', 'api_key' => 'clave-api', 'usuario' => 'toes_prueba', 'clave' => 'secreta', 'timeout' => 5]);
    }

    public function test_el_precio_de_venta_es_el_coste_mas_el_margen_redondeado_al_alza_a_cinco_centimos(): void
    {
        $this->assertSame(1170, PrecioConMargen::venta(650, 80));   // 6,50 € + 80 % = 11,70 €
        $this->assertSame(1145, PrecioConMargen::venta(636, 80));   // 11,448 € → 11,45 €
        $this->assertSame(1150, PrecioConMargen::venta(637, 80));   // 11,466 € → 11,50 €
        $this->assertSame(500, PrecioConMargen::venta(500, 0));
        $this->assertSame(0, PrecioConMargen::venta(0, 80));
    }

    public function test_cada_prenda_puede_tener_su_margen_y_si_no_usa_el_general(): void
    {
        Ajuste::guardar('proveedores.margen', 60);
        $prenda = new Prenda;
        $this->assertSame(60, PrecioConMargen::margen($prenda));
        $prenda->margen = 100;
        $this->assertSame(100, PrecioConMargen::margen($prenda));
        $this->assertSame(60, PrecioConMargen::margen());
    }

    public function test_pide_el_token_una_vez_y_lo_manda_con_la_api_key_en_cada_llamada(): void
    {
        Http::fake([
            'api.toptex.io/v3/authenticate' => Http::response(['username' => 'toes_prueba', 'token' => 'tok-1', 'expiry_time' => now()->addHour()->toIso8601String()]),
            'api.toptex.io/v3/products/price*' => Http::response([
                ['sku' => 'GI6400BC_43989_37623', 'catalogReference' => 'GI6400B', 'prices' => [
                    ['quantity' => '50', 'price' => 5.80], ['quantity' => '1', 'price' => 6.50], ['quantity' => '100', 'price' => 5.10],
                ]],
                ['sku' => 'GI6400BC_43989_37624', 'catalogReference' => 'GI6400B', 'prices' => [['quantity' => '1', 'price' => 7.05]]],
            ]),
        ]);
        $toptex = app(ClienteTopTex::class);

        $this->assertSame(['GI6400BC_43989_37623' => 650, 'GI6400BC_43989_37624' => 705], $toptex->costes('GI6400B'));
        $toptex->costes('GI6400B');

        Http::assertSentCount(3); // un token y dos consultas
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.toptex.io/v3/authenticate' && $r->hasHeader('x-api-key', 'clave-api')
            && $r['username'] === 'toes_prueba' && $r['password'] === 'secreta');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'catalog_reference=GI6400B') && $r->hasHeader('x-toptex-authorization', 'tok-1') && $r->hasHeader('x-api-key', 'clave-api'));
    }

    public function test_si_toptex_rechaza_el_token_pide_otro_y_repite(): void
    {
        Http::fake([
            'api.toptex.io/v3/authenticate' => Http::sequence()->push(['token' => 'viejo'])->push(['token' => 'nuevo']),
            'api.toptex.io/v3/products/price*' => Http::sequence()->push([], 401)->push([['sku' => 'A', 'prices' => [['quantity' => '1', 'price' => 3]]]]),
        ]);

        $this->assertSame(['A' => 300], app(ClienteTopTex::class)->costes('X'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'price') && $r->hasHeader('x-toptex-authorization', 'nuevo'));
    }

    public function test_sin_credenciales_o_con_credenciales_malas_da_un_aviso_claro(): void
    {
        Http::fake(['api.toptex.io/v3/authenticate' => Http::response(['message' => 'Forbidden'], 403)]);
        try {
            app(ClienteTopTex::class)->probar();
            $this->fail('Debía fallar');
        } catch (ProveedorNoDisponible $e) {
            $this->assertStringContainsString('rechaza', $e->getMessage());
        }

        config()->set('toptex.clave', null);
        $this->assertFalse(ClienteTopTex::configurado());
        $this->expectException(ProveedorNoDisponible::class);
        app(ClienteTopTex::class)->probar();
    }

    public function test_ajustes_proveedores_guarda_el_margen_y_prueba_la_conexion(): void
    {
        Http::fake(['api.toptex.io/v3/authenticate' => Http::response(['username' => 'toes_prueba', 'token' => 'tok'])]);
        $this->actingAs($this->usuarioConRol(Rol::Administrador));

        Livewire::test(AjustesProveedores::class)->assertSee('se vende a 9,00 €')->assertDontSee('secreta')
            ->set('data.margen', 50)->callAction('guardar')->assertHasNoErrors()
            ->callAction('probar')->assertNotified('Conexión correcta');

        $this->assertSame(50, (int) Ajuste::obtener('proveedores.margen'));
    }
}
