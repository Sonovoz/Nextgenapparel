<?php

namespace Tests\Feature\Clientes;

use App\Dominio\Marketing\AvisarCarritos;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Pages\CarritosAbandonados;
use App\Models\Ajuste;
use App\Models\AvisoCarrito;
use App\Models\Carrito;
use App\Models\Cliente;
use App\Models\Cupon;
use App\Models\Familia;
use App\Models\Prenda;
use App\Models\User;
use App\Notifications\Marketing\CarritoAbandonado;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Carritos abandonados (v2, D189): secuencia 1 h, 24 h y 72 h (con cupón), solo con consentimiento comercial. */
class CarritosAbandonadosTest extends TestCase
{
    use RefreshDatabase;

    private Cliente $cliente;

    private Carrito $carrito;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Cupon::create(['codigo' => 'VUELVE10', 'tipo' => 'porcentaje', 'valor' => 1000, 'activo' => true]);
        Ajuste::guardar('carritos.avisos_activos', true);
        Ajuste::guardar('carritos.secuencia', [['horas' => 1, 'cupon' => null], ['horas' => 24, 'cupon' => null], ['horas' => 72, 'cupon' => 'VUELVE10']]);

        $usuario = User::factory()->create(['email' => 'ana@ejemplo.com', 'email_verified_at' => now()]);
        $this->cliente = Cliente::factory()->create(['user_id' => $usuario->id, 'correo' => 'ana@ejemplo.com', 'marketing_consentido_en' => now()]);
        $this->carrito = Carrito::create(['cliente_id' => $this->cliente->id]);
        $this->carrito->lineas()->create(['tipo' => 'prenda', 'prenda_id' => Prenda::factory()->for(Familia::factory()->create())->create()->id, 'tallas' => [1 => 15]]);
        $this->viajarA(0);
    }

    /** El carrito se tocó por última vez hace $horas horas. */
    private function viajarA(int $horas): void
    {
        $this->carrito->forceFill(['updated_at' => now()->subHours($horas)])->saveQuietly();
    }

    public function test_la_secuencia_avisa_a_su_hora_una_vez_y_el_ultimo_con_cupon(): void
    {
        $this->assertSame(0, app(AvisarCarritos::class)()); // recién tocado
        $this->viajarA(2);
        $this->assertSame(1, app(AvisarCarritos::class)());
        $this->assertSame(0, app(AvisarCarritos::class)()); // el de 1 h ya salió; el de 24 h aún no toca
        $this->viajarA(25);
        $this->assertSame(1, app(AvisarCarritos::class)());
        $this->viajarA(80);
        $this->assertSame(1, app(AvisarCarritos::class)());
        $this->assertSame(0, app(AvisarCarritos::class)()); // se acabó la secuencia

        $this->assertSame([0, 1, 2], AvisoCarrito::orderBy('paso')->pluck('paso')->all());
        $this->assertSame('VUELVE10', AvisoCarrito::where('paso', 2)->value('cupon'));
        Notification::assertSentOnDemandTimes(CarritoAbandonado::class, 3);
        Notification::assertSentOnDemand(CarritoAbandonado::class, fn (CarritoAbandonado $n) => $n->cupon === 'VUELVE10'
            && str_contains(implode(' ', $n->toMail(new AnonymousNotifiable)->introLines), 'VUELVE10'));
    }

    public function test_si_vuelve_a_tocar_el_carrito_la_secuencia_empieza_de_nuevo(): void
    {
        $this->viajarA(2);
        app(AvisarCarritos::class)();
        $this->travel(1)->days();
        $this->carrito->touch(); // vuelve y cambia algo
        $this->travel(2)->hours();

        $this->assertSame(1, app(AvisarCarritos::class)()); // otra vez el de 1 h, no el de 24 h
        $this->assertSame([0], AvisoCarrito::pluck('paso')->all());
    }

    public static function sinAviso(): array
    {
        return [
            'sin consentimiento comercial' => [fn (self $t) => $t->cliente->update(['marketing_consentido_en' => null])],
            'correo sin verificar' => [fn (self $t) => $t->cliente->usuario->forceFill(['email_verified_at' => null])->save()],
            'carrito vacío' => [fn (self $t) => $t->carrito->lineas()->delete()],
            'avisos desactivados' => [fn () => Ajuste::guardar('carritos.avisos_activos', false)],
        ];
    }

    #[DataProvider('sinAviso')]
    public function test_no_se_avisa(callable $preparar): void
    {
        $preparar($this);
        $this->viajarA(2);

        $this->assertSame(0, app(AvisarCarritos::class)());
        Notification::assertNothingSent();
    }

    public function test_el_enlace_de_baja_quita_el_consentimiento(): void
    {
        $this->get(URL::signedRoute('marketing.baja', ['cliente' => $this->cliente->uuid]))->assertOk()->assertSee(__('marketing.baja_hecha'));
        $this->assertNull($this->cliente->fresh()->marketing_consentido_en);
        $this->get(route('marketing.baja', ['cliente' => $this->cliente->uuid]))->assertForbidden(); // sin firma
    }

    public function test_el_equipo_de_marketing_configura_la_secuencia(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda))->get(CarritosAbandonados::getUrl())->assertOk()->assertSee('Clientes avisados');
        $this->actingAs($this->usuarioConRol(Rol::Produccion))->get(CarritosAbandonados::getUrl())->assertForbidden();
    }
}
