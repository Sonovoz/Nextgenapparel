<?php

namespace Tests\Feature\Sistema;

use App\Dominio\Sistema\Acceso\Rol;
use App\Dominio\Sistema\Errores\ErrorDePrueba;
use App\Dominio\Sistema\Errores\EstadoError;
use App\Dominio\Sistema\Errores\NivelError;
use App\Dominio\Sistema\Errores\RegistrarError;
use App\Filament\Resources\RegistroErrors\Pages\ListRegistroErrors;
use App\Filament\Resources\RegistroErrors\Pages\ViewRegistroError;
use App\Models\RegistroError;
use App\Notifications\AlertaErrorCritico;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class LogErroresTest extends TestCase
{
    use RefreshDatabase;

    private const CORREO = 'alertas@ejemplo.test';

    protected function setUp(): void
    {
        parent::setUp();

        config(['errores.correo_alertas' => self::CORREO, 'errores.alerta_cada_minutos' => 60]);
        Notification::fake();
        Filament::setCurrentPanel('admin');
        @unlink(storage_path('framework/alertas-sin-base-de-datos.json'));
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('framework/alertas-sin-base-de-datos.json'));

        parent::tearDown();
    }

    /** Siempre el mismo punto del código, para que la huella coincida. */
    private function errorRepetido(string $mensaje = 'Fallo de prueba'): RuntimeException
    {
        return new RuntimeException($mensaje);
    }

    private function alertasEnviadas(): int
    {
        return Notification::sent(new AnonymousNotifiable, AlertaErrorCritico::class)->count();
    }

    public function test_un_error_queda_registrado_como_pendiente(): void
    {
        report($this->errorRepetido());

        $registro = RegistroError::sole();
        $this->assertSame(NivelError::Error, $registro->nivel);
        $this->assertSame(EstadoError::Pendiente, $registro->estado);
        $this->assertSame(1, $registro->ocurrencias);
        $this->assertSame(RuntimeException::class, $registro->clase);
        $this->assertSame('tests/Feature/Sistema/LogErroresTest.php', $registro->archivo);
        $this->assertNotEmpty($registro->traza);
    }

    public function test_el_mismo_error_se_agrupa_y_cuenta_aunque_cambie_el_mensaje(): void
    {
        report($this->errorRepetido('Pedido 1 no encontrado'));
        report($this->errorRepetido('Pedido 2 no encontrado'));
        report($this->errorRepetido('Pedido 3 no encontrado'));

        $registro = RegistroError::sole();
        $this->assertSame(3, $registro->ocurrencias);
        $this->assertSame('Pedido 3 no encontrado', $registro->mensaje);
    }

    public function test_errores_en_sitios_distintos_son_filas_distintas(): void
    {
        report(new RuntimeException('A'));
        report(new RuntimeException('B'));

        $this->assertSame(2, RegistroError::count());
    }

    public function test_los_errores_internos_de_php_y_los_de_base_de_datos_son_criticos(): void
    {
        $this->assertSame(NivelError::Critico, RegistrarError::nivel(new \TypeError('x')));
        $this->assertSame(NivelError::Critico, RegistrarError::nivel(new \PDOException('x')));
        $this->assertSame(NivelError::Error, RegistrarError::nivel(new RuntimeException('x')));
    }

    public function test_un_error_critico_avisa_por_correo(): void
    {
        report(new ErrorDePrueba('Prueba'));

        Notification::assertSentOnDemand(AlertaErrorCritico::class, function (AlertaErrorCritico $n, array $canales, object $destino): bool {
            return $destino->routes['mail'] === self::CORREO;
        });
        $this->assertNotNull(RegistroError::sole()->alertado_en);
    }

    public function test_un_error_normal_no_avisa(): void
    {
        report($this->errorRepetido());

        $this->assertSame(0, $this->alertasEnviadas());
    }

    public function test_el_mismo_error_critico_no_vuelve_a_avisar_durante_una_hora(): void
    {
        $error = fn () => new ErrorDePrueba('Prueba');

        report($error());
        report($error());
        $this->travel(59)->minutes();
        report($error());

        $this->assertSame(1, $this->alertasEnviadas());

        $this->travel(2)->minutes();
        report($error());

        $this->assertSame(2, $this->alertasEnviadas());
        $this->assertSame(4, RegistroError::sole()->ocurrencias);
    }

    public function test_un_error_resuelto_que_vuelve_se_reabre_y_avisa(): void
    {
        $error = fn () => new ErrorDePrueba('Prueba');
        report($error());
        RegistroError::sole()->marcarResuelto($this->usuarioConRol());

        report($error());

        $registro = RegistroError::sole();
        $this->assertSame(EstadoError::Pendiente, $registro->estado);
        $this->assertNull($registro->resuelto_en);
        $this->assertSame(2, $this->alertasEnviadas());
    }

    public function test_sin_correo_configurado_no_avisa_ni_falla(): void
    {
        config(['errores.correo_alertas' => null]);

        report(new ErrorDePrueba('Prueba'));

        $this->assertSame(1, RegistroError::count());
        $this->assertSame(0, $this->alertasEnviadas());
    }

    public function test_no_se_registran_404_ni_errores_de_validacion(): void
    {
        $this->get('/no-existe')->assertNotFound();
        report(ValidationException::withMessages(['campo' => 'mal']));

        $this->assertSame(0, RegistroError::count());
    }

    public function test_un_error_en_una_pagina_guarda_la_url_sin_la_consulta_y_el_usuario(): void
    {
        Route::get('/prueba-error', fn () => throw new RuntimeException('Fallo en página'))->middleware('web');
        $usuario = $this->usuarioConRol();

        $this->actingAs($usuario)->get('/prueba-error?token=secreto')->assertServerError();

        $registro = RegistroError::sole();
        $this->assertSame(url('/prueba-error'), $registro->url);
        $this->assertSame('GET', $registro->metodo);
        $this->assertSame($usuario->id, $registro->usuario_id);
    }

    public function test_en_una_accion_del_backend_guarda_la_pagina_y_no_la_ruta_interna(): void
    {
        Route::post('/livewire-prueba/update', fn () => throw new RuntimeException('Fallo en acción'))->middleware('web');

        $this->withHeaders(['X-Livewire' => '1', 'Referer' => url('/admin/usuarios?pagina=2')])
            ->post('/livewire-prueba/update')
            ->assertServerError();

        $this->assertSame(url('/admin/usuarios'), RegistroError::sole()->url);
    }

    public function test_con_la_base_de_datos_caida_avisa_igualmente_una_sola_vez(): void
    {
        Schema::drop('registro_errores');
        $error = fn () => new ErrorDePrueba('Prueba sin base de datos');

        report($error());
        report($error());

        $this->assertSame(1, $this->alertasEnviadas());
        Notification::assertSentOnDemand(AlertaErrorCritico::class, fn (AlertaErrorCritico $n) => ! $n->registro->exists);
    }

    public function test_el_backend_se_abre_aunque_falte_la_tabla_de_errores(): void
    {
        Schema::drop('registro_errores');

        $this->actingAs($this->usuarioConRol())->get('/admin')->assertOk();
    }

    public function test_registrar_nunca_lanza_excepciones(): void
    {
        Schema::drop('registro_errores');

        try {
            (new RegistrarError)(new RuntimeException('x'));
        } catch (Throwable $e) {
            $this->fail('No debería lanzar: '.$e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_los_errores_resueltos_antiguos_se_eliminan(): void
    {
        report(new RuntimeException('Viejo'));
        report(new RuntimeException('Pendiente'));
        RegistroError::where('mensaje', 'Viejo')->first()->marcarResuelto($this->usuarioConRol());
        $this->travel(91)->days();

        $this->artisan('model:prune', ['--model' => [RegistroError::class]])->assertSuccessful();

        $this->assertSame(['Pendiente'], RegistroError::pluck('mensaje')->all());
    }

    public function test_el_correo_de_alerta_es_claro(): void
    {
        report(new ErrorDePrueba('Algo ha fallado'));
        $correo = (new AlertaErrorCritico(RegistroError::sole()))->toMail(new AnonymousNotifiable);

        $this->assertStringContainsString('Error crítico en NextGen Apparel', $correo->subject);
        $this->assertStringContainsString('[testing]', $correo->subject);
        $this->assertStringContainsString('/admin/log-errores/', $correo->actionUrl);
    }

    public function test_el_backend_lista_los_errores_pendientes(): void
    {
        report($this->errorRepetido('Visible en la lista'));
        $this->actingAs($this->usuarioConRol(Rol::Administrador));

        Livewire::test(ListRegistroErrors::class)
            ->assertOk()
            ->assertCanSeeTableRecords(RegistroError::all())
            ->assertSee('Visible en la lista');
    }

    public function test_el_superadministrador_puede_provocar_un_error_de_prueba(): void
    {
        // Durante las llamadas de Livewire en los tests el gestor de
        // excepciones no registra; se comprueba que el botón lo reporta por
        // el camino normal. Que ese camino guarda y avisa ya lo cubren los
        // tests anteriores y el comando nga:error-prueba.
        Exceptions::fake();
        $this->actingAs($this->usuarioConRol());

        Livewire::test(ListRegistroErrors::class)
            ->assertActionVisible('errorDePrueba')
            ->callAction('errorDePrueba')
            ->assertNotified('Error de prueba registrado');

        Exceptions::assertReported(ErrorDePrueba::class);
    }

    public function test_un_administrador_no_ve_el_boton_de_error_de_prueba(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::Administrador));

        Livewire::test(ListRegistroErrors::class)->assertActionHidden('errorDePrueba');
    }

    public function test_marcar_como_resuelto_y_reabrir_desde_el_detalle(): void
    {
        report($this->errorRepetido());
        $registro = RegistroError::sole();
        $this->actingAs($admin = $this->usuarioConRol(Rol::Administrador));

        Livewire::test(ViewRegistroError::class, ['record' => $registro->getRouteKey()])
            ->callAction('marcarResuelto');

        $registro->refresh();
        $this->assertSame(EstadoError::Resuelto, $registro->estado);
        $this->assertSame($admin->id, $registro->resuelto_por);

        Livewire::test(ViewRegistroError::class, ['record' => $registro->getRouteKey()])
            ->callAction('reabrir');

        $this->assertSame(EstadoError::Pendiente, $registro->fresh()->estado);
    }

    public function test_el_gestor_y_produccion_no_ven_el_log(): void
    {
        foreach ([Rol::GestorTienda, Rol::Produccion] as $rol) {
            $this->actingAs($this->usuarioConRol($rol))->get('/admin/log-errores')->assertForbidden();
        }
    }

    public function test_el_comando_de_prueba_registra_y_avisa(): void
    {
        $this->artisan('nga:error-prueba')->assertSuccessful();

        $this->assertSame(ErrorDePrueba::class, RegistroError::sole()->clase);
        $this->assertSame(1, $this->alertasEnviadas());
    }
}
