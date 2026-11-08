<?php

namespace Tests\Feature\Carrito;

use App\Dominio\Carrito\CarritoActual;
use App\Models\Ajuste;
use App\Models\Carrito;
use App\Models\Cliente;
use App\Models\Color;
use App\Models\Direccion;
use App\Models\Familia;
use App\Models\Prenda;
use App\Models\Talla;
use App\Models\TarifaEnvio;
use App\Models\User;
use App\Models\Variante;
use App\Models\ZonaEnvio;
use App\Models\ZonaImpresion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Cierra el pedido hasta el total (paso 5.8): el invitado da sus datos, con cuenta salen
 * de su ficha, y el motor calcula envío, IVA, recargo y total con el destino.
 */
class CierreTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $negro = Color::factory()->create();
        $m = Talla::factory()->create(['nombre' => 'M']);
        $zona = ZonaImpresion::factory()->create(['ancho_max_mm' => 280, 'alto_max_mm' => 380, 'desde_cuello_mm' => 75]);
        $prenda = Prenda::factory()->for(Familia::factory()->create())->create();
        Variante::factory()->for($prenda)->create(['color_id' => $negro->id, 'talla_id' => $m->id, 'precio_base' => 400, 'peso_gramos' => 150]);
        $prenda->zonas()->attach($zona->id, ['precio' => 300, 'coste' => 100]);
        // Península: 4,90 € hasta 2 kg (caso E de la ficha).
        $peninsula = ZonaEnvio::create(['nombre' => ['es' => 'Península'], 'paises' => ['ES'], 'metodo' => 'por_kilo', 'precio_kg_extra' => 80, 'plazo_dias' => 2]);
        TarifaEnvio::create(['zona_envio_id' => $peninsula->id, 'hasta_gramos' => 5000, 'precio' => 490]);

        $carrito = Carrito::create(['token' => hash('sha256', $this->token = str_repeat('t', 40))]);
        $linea = $carrito->lineas()->create(['tipo' => 'prenda', 'prenda_id' => $prenda->id, 'color_id' => $negro->id, 'tallas' => [$m->id => 15]]);
        $linea->impresiones()->create(['zona_impresion_id' => $zona->id, 'texto' => 'NG', 'fuente' => 0, 'tinta' => 'auto', 'ancho_mm' => 100, 'alto_mm' => 60, 'desde_cuello_mm' => 75, 'desde_centro_mm' => 0]);
        $this->withCookie(CarritoActual::COOKIE, $this->token);
    }

    public function test_el_invitado_da_sus_datos_y_ve_el_envio_y_el_total(): void
    {
        $this->get('/es/carrito')->assertSee('/es/carrito/cerrar-pedido', false);
        $this->get('/es/carrito/cerrar-pedido')->assertOk()->assertSee('Cierra tu pedido')->assertSee('noindex', false);

        $this->post('/es/carrito/cerrar-pedido', $this->datos())->assertRedirect('/es/carrito/cerrar-pedido#total')->assertSessionHasNoErrors();

        // 15 × 7,00 = 105,00 + envío 4,90 = 109,90 de base; IVA 21 % = 23,08; total 132,98.
        $this->get('/es/carrito/cerrar-pedido')->assertSee('Península')->assertSee('4,90 €')->assertSee('132,98 €')->assertDontSee('Recargo de equivalencia');
        $this->get('/en/cart/checkout')->assertOk()->assertSee('Checkout');
    }

    /** @return array<string, array{0: array<string, string>, 1: string}> */
    public static function datosNoValidos(): array
    {
        return [
            'empresa sin CIF' => [['tipo' => 'empresa', 'nif' => ''], 'nif'],
            'CIF mal escrito' => [['tipo' => 'empresa', 'nif' => 'B12345670'], 'nif'],
            'código postal de otro país' => [['codigo_postal' => 'SW1A 1AA'], 'codigo_postal'],
            'sin teléfono' => [['telefono' => ''], 'telefono'],
            'correo no válido' => [['correo' => 'ana@'], 'correo'],
        ];
    }

    #[DataProvider('datosNoValidos')]
    public function test_los_datos_se_validan(array $cambios, string $campo): void
    {
        $this->from('/es/carrito/cerrar-pedido')->post('/es/carrito/cerrar-pedido', $this->datos($cambios))->assertSessionHasErrors($campo);
    }

    public function test_el_invitado_solo_aplica_recargo_si_el_backend_lo_deja_en_manos_del_cliente(): void
    {
        $empresa = $this->datos(['tipo' => 'empresa', 'nif' => 'B12345674', 'recargo' => '1']);
        $this->post('/es/carrito/cerrar-pedido', $empresa);
        $this->assertFalse(session('cierre.recargo'));

        Ajuste::guardar('clientes.recargo_lo_indica', 'cliente');
        $this->post('/es/carrito/cerrar-pedido', $empresa);
        $this->assertTrue(session('cierre.recargo'));
        $this->get('/es/carrito/cerrar-pedido')->assertSee('Recargo de equivalencia')->assertSee('5,71 €'); // 5,2 % de 109,90
    }

    public function test_con_cuenta_usa_su_ficha_su_direccion_y_se_bloquea_si_falta_algo(): void
    {
        $usuario = User::factory()->create(['email' => 'ana@ejemplo.com']);
        $cliente = Cliente::factory()->create(['user_id' => $usuario->id, 'correo' => 'ana@ejemplo.com', 'nombre' => 'Ana García', 'tipo' => 'empresa', 'nif' => 'B12345674', 'recargo_equivalencia' => true]);
        Carrito::query()->update(['token' => null, 'cliente_id' => $cliente->id]);
        Direccion::create(['cliente_id' => $cliente->id, 'tipo' => 'envio', 'nombre' => 'Ana García', 'direccion' => 'Calle Mayor 1', 'codigo_postal' => '28013', 'localidad' => 'Madrid', 'pais' => 'ES', 'predeterminada' => true]);

        $this->actingAs($usuario)->get('/es/carrito/cerrar-pedido')->assertSee('B12345674')->assertSee('Calle Mayor 1')->assertDontSee('name="correo"', false);
        $this->post('/es/carrito/cerrar-pedido', [...$this->datos(), 'recargo' => '0'])->assertSessionHasNoErrors();
        $this->assertTrue(session('cierre.recargo')); // el de su ficha, no el del formulario

        $cliente->update(['nif' => null]);
        $this->actingAs($usuario->fresh())->get('/es/carrito/cerrar-pedido')->assertSee('necesitamos tu NIF o CIF')->assertDontSee('Calcular envío y total');
        $usuario->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($usuario->fresh())->get('/es/carrito/cerrar-pedido')->assertSee('confirma tu correo');
    }

    public function test_el_cierre_no_usa_datos_viejos_de_la_sesion(): void
    {
        Ajuste::guardar('clientes.recargo_lo_indica', 'cliente');
        $this->post('/es/carrito/cerrar-pedido', $this->datos(['tipo' => 'empresa', 'nif' => 'B12345674', 'recargo' => '1']));
        $this->get('/es/carrito/cerrar-pedido')->assertSee('Recargo de equivalencia');

        // Entra en una cuenta sin recargo: lo calculado como invitado no vale.
        $usuario = User::factory()->create(['email' => 'ana@ejemplo.com']);
        $cliente = Cliente::factory()->create(['user_id' => $usuario->id, 'correo' => 'ana@ejemplo.com', 'tipo' => 'empresa', 'nif' => 'B12345674', 'recargo_equivalencia' => false]);
        Carrito::query()->update(['token' => null, 'cliente_id' => $cliente->id]);
        $this->actingAs($usuario)->get('/es/carrito/cerrar-pedido')->assertDontSee('Recargo de equivalencia')->assertDontSee('invitada@ejemplo.com');
        $this->assertNull(session('cierre'));

        // Con cuenta, el recargo es siempre el de su ficha, aunque cambie después de calcular.
        $this->post('/es/carrito/cerrar-pedido', $this->datos());
        $cliente->update(['recargo_equivalencia' => true]);
        $this->actingAs($usuario->fresh())->get('/es/carrito/cerrar-pedido')->assertSee('Recargo de equivalencia');
    }

    public function test_sin_carrito_vuelve_al_carrito(): void
    {
        Carrito::query()->delete();
        $this->get('/es/carrito/cerrar-pedido')->assertRedirect('/es/carrito');
    }

    private function datos(array $cambios = []): array
    {
        return [...['correo' => 'invitada@ejemplo.com', 'tipo' => 'particular', 'nif' => '', 'nombre' => 'Lucía Pérez', 'direccion' => 'Calle Luna 3',
            'codigo_postal' => '28004', 'localidad' => 'Madrid', 'pais' => 'ES', 'telefono' => '600 123 123'], ...$cambios];
    }
}
