<?php

namespace Tests\Feature\Clientes;

use App\Dominio\Sistema\Acceso\Rol;
use App\Models\Cliente;
use App\Models\Diseno;
use App\Models\User;
use App\Notifications\Cuenta\RestablecerContrasena;
use App\Notifications\Cuenta\VerificarCorreo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\PngDePrueba;
use Tests\TestCase;

/** Cuenta del cliente (paso 5.2): registro, entrar, salir, contraseña y verificación. */
class CuentaTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'contrasena-segura-1';

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    /* ---------- Registro ---------- */

    public static function idiomas(): array
    {
        return [
            'español' => ['es', '/es/cuenta/registro', '/es/cuenta'],
            'inglés' => ['en', '/en/account/register', '/en/account'],
        ];
    }

    #[DataProvider('idiomas')]
    public function test_el_registro_pide_lo_minimo_entra_y_envia_la_verificacion_en_su_idioma(string $idioma, string $url, string $cuenta): void
    {
        $this->post($url, $this->datosRegistro(['correo' => '  Ana@Ejemplo.COM ', 'tipo' => 'autonomo']))
            ->assertRedirect($cuenta);

        $usuario = User::where('email', 'ana@ejemplo.com')->sole();
        $this->assertAuthenticatedAs($usuario);
        $this->assertFalse($usuario->hasVerifiedEmail());
        $this->assertSame([], $usuario->getRoleNames()->all(), 'Un cliente no tiene rol del backend');
        $this->assertDatabaseHas('clientes', ['user_id' => $usuario->id, 'correo' => 'ana@ejemplo.com', 'tipo' => 'autonomo', 'idioma' => $idioma, 'nif' => null, 'marketing_consentido_en' => null]);
        Notification::assertSentTo($usuario, VerificarCorreo::class, fn ($n) => $n->locale === $idioma);
        $this->assertSame(0, Activity::where('log_name', 'usuarios')->count(), 'Las altas de clientes no van al registro del equipo');
    }

    public function test_el_consentimiento_de_marketing_se_guarda_con_fecha_y_origen_solo_si_se_marca(): void
    {
        $this->post('/es/cuenta/registro', $this->datosRegistro(['marketing' => '1']));

        $cliente = Cliente::sole();
        $this->assertNotNull($cliente->marketing_consentido_en);
        $this->assertSame('registro', $cliente->marketing_origen);
    }

    public static function registrosNoValidos(): array
    {
        return [
            'correo ya registrado (otras mayúsculas)' => [['correo' => 'EMPLEADO@nga.test'], 'correo'],
            'contraseña corta' => [['contrasena' => 'abc123', 'contrasena_confirmation' => 'abc123'], 'contrasena'],
            'contraseña sin números' => [['contrasena' => 'solamenteletras', 'contrasena_confirmation' => 'solamenteletras'], 'contrasena'],
            'contraseñas distintas' => [['contrasena_confirmation' => 'otra-cosa-123'], 'contrasena'],
            'tipo inventado' => [['tipo' => 'mayorista'], 'tipo'],
            'sin nombre' => [['nombre' => ''], 'nombre'],
        ];
    }

    #[DataProvider('registrosNoValidos')]
    public function test_el_registro_rechaza_datos_no_validos(array $cambios, string $campo): void
    {
        User::factory()->create(['email' => 'empleado@nga.test']);

        $this->from('/es/cuenta/registro')->post('/es/cuenta/registro', $this->datosRegistro($cambios))
            ->assertRedirect('/es/cuenta/registro')->assertSessionHasErrors($campo);
        $this->assertGuest();
    }

    /* ---------- Entrar y salir ---------- */

    public function test_entra_con_el_correo_en_cualquier_mayuscula_y_renueva_la_sesion(): void
    {
        $usuario = $this->cliente();
        $this->get('/es/cuenta/entrar');
        $antes = session()->getId();

        $this->post('/es/cuenta/entrar', ['correo' => 'CLIENTE@ejemplo.com', 'contrasena' => self::CLAVE])->assertRedirect('/es/cuenta');

        $this->assertAuthenticatedAs($usuario);
        $this->assertNotSame($antes, session()->getId());
    }

    public static function accesosFallidos(): array
    {
        return [
            'contraseña equivocada' => ['cliente@ejemplo.com', 'otra-clave-123', true],
            'correo que no existe' => ['nadie@ejemplo.com', self::CLAVE, true],
            'cuenta desactivada' => ['cliente@ejemplo.com', self::CLAVE, false],
        ];
    }

    #[DataProvider('accesosFallidos')]
    public function test_un_acceso_fallido_da_siempre_el_mismo_mensaje(string $correo, string $clave, bool $activo): void
    {
        $this->cliente(['activo' => $activo]);

        $this->from('/es/cuenta/entrar')->post('/es/cuenta/entrar', ['correo' => $correo, 'contrasena' => $clave])
            ->assertSessionHasErrors(['correo' => __('cuenta.credenciales')]);
        $this->assertGuest();
    }

    public function test_tras_cinco_intentos_fallidos_hay_que_esperar(): void
    {
        $this->cliente();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/es/cuenta/entrar', ['correo' => 'cliente@ejemplo.com', 'contrasena' => 'mal-'.$i]);
        }

        $this->post('/es/cuenta/entrar', ['correo' => 'cliente@ejemplo.com', 'contrasena' => self::CLAVE])->assertSessionHasErrors('correo');
        $this->assertGuest();
    }

    public function test_los_accesos_de_clientes_no_van_al_registro_del_equipo_y_los_del_backend_si(): void
    {
        $this->cliente();
        $this->post('/es/cuenta/entrar', ['correo' => 'cliente@ejemplo.com', 'contrasena' => 'mal-1']);
        $this->post('/es/cuenta/entrar', ['correo' => 'cliente@ejemplo.com', 'contrasena' => self::CLAVE]);

        $this->assertSame(0, Activity::where('log_name', 'accesos')->count());
    }

    public function test_salir_cierra_la_sesion(): void
    {
        $this->actingAs($this->cliente())->post('/es/cuenta/salir')->assertRedirect('/es');

        $this->assertGuest();
    }

    public function test_al_entrar_los_disenos_de_la_sesion_pasan_a_la_cuenta_sin_duplicar(): void
    {
        $usuario = $this->cliente();
        $cliente = $usuario->cliente;
        $this->withSession(['disenos.propietario' => 'token-de-la-sesion']);
        $propietario = hash('sha256', 'token-de-la-sesion');
        $nuevo = $this->diseno(['propietario' => $propietario, 'sha256' => str_repeat('a', 64)]);
        $repetido = $this->diseno(['propietario' => $propietario, 'sha256' => str_repeat('b', 64)]);
        $suyo = $this->diseno(['cliente_id' => $cliente->id, 'propietario' => null, 'sha256' => str_repeat('b', 64)]);
        $ajeno = $this->diseno(['propietario' => hash('sha256', 'otra-sesion'), 'sha256' => str_repeat('c', 64)]);

        $this->post('/es/cuenta/entrar', ['correo' => 'cliente@ejemplo.com', 'contrasena' => self::CLAVE]);

        $this->assertSame($cliente->id, $nuevo->fresh()->cliente_id);
        $this->assertNull($nuevo->fresh()->propietario);
        $this->assertModelMissing($repetido);
        $this->assertModelExists($suyo);
        $this->assertNull($ajeno->fresh()->cliente_id);
        $this->get('/es/cuenta')->assertSee($nuevo->nombre_original)->assertDontSee('ajeno.png');
    }

    public function test_con_la_sesion_iniciada_lo_que_se_sube_es_del_cliente(): void
    {
        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('Falta la extensión Imagick.');
        }
        Storage::fake('disenos');
        $usuario = $this->cliente();

        $uuid = $this->actingAs($usuario)->postJson('/es/disenos', [
            'archivo' => new UploadedFile(PngDePrueba::crear(600, 600, 'white'), 'logo.png', 'image/png', null, true),
            'derechos' => '1',
        ])->assertCreated()->json('uuid');

        $diseno = Diseno::where('uuid', $uuid)->sole();
        $this->assertSame($usuario->cliente->id, $diseno->cliente_id);
        $this->assertNull($diseno->propietario);
        $this->getJson('/es/disenos')->assertJsonPath('disenos.0.uuid', $uuid);
        $this->get('/es/cuenta')->assertSee('logo.png');
    }

    public function test_cada_formulario_tiene_su_propio_limite_y_superarlo_da_un_aviso_legible(): void
    {
        // Antes, «throttle:6,1» compartía el contador de la IP con el registro y el acceso.
        for ($i = 0; $i < 8; $i++) {
            $this->post('/es/cuenta/registro', $this->datosRegistro(['correo' => "persona{$i}@ejemplo.com"]));
            $this->post('/es/cuenta/salir');
        }
        $this->from('/es/cuenta/recuperar-contrasena')->post('/es/cuenta/recuperar-contrasena', ['correo' => 'persona1@ejemplo.com'])
            ->assertRedirect('/es/cuenta/recuperar-contrasena')->assertSessionHas('estado', __('cuenta.enlace_enviado'));

        for ($i = 0; $i < 6; $i++) {
            $this->post('/es/cuenta/recuperar-contrasena', ['correo' => 'persona1@ejemplo.com']);
        }
        $this->from('/es/cuenta/recuperar-contrasena')->post('/es/cuenta/recuperar-contrasena', ['correo' => 'persona1@ejemplo.com'])
            ->assertRedirect('/es/cuenta/recuperar-contrasena')->assertSessionHas('error');
    }

    /* ---------- Contraseña ---------- */

    public function test_recuperar_responde_igual_exista_o_no_la_cuenta(): void
    {
        $usuario = $this->cliente();

        foreach (['cliente@ejemplo.com', 'nadie@ejemplo.com'] as $correo) {
            $this->from('/es/cuenta/recuperar-contrasena')->post('/es/cuenta/recuperar-contrasena', ['correo' => $correo])
                ->assertRedirect('/es/cuenta/recuperar-contrasena')->assertSessionHas('estado', __('cuenta.enlace_enviado'));
        }

        Notification::assertSentTo($usuario, RestablecerContrasena::class, function (RestablecerContrasena $n) use ($usuario) {
            $url = $n->toMail($usuario)->actionUrl;

            return str_contains($url, '/es/cuenta/nueva-contrasena/'.$n->token) && str_contains($url, 'correo=cliente%40ejemplo.com');
        });
        Notification::assertCount(1);
    }

    public function test_con_el_enlace_se_cambia_la_contrasena_y_el_correo_queda_verificado(): void
    {
        $usuario = $this->cliente(['email_verified_at' => null]);
        $token = Password::createToken($usuario);

        $this->post('/es/cuenta/nueva-contrasena', ['token' => $token, 'correo' => 'cliente@ejemplo.com', 'contrasena' => 'nueva-clave-456', 'contrasena_confirmation' => 'nueva-clave-456'])
            ->assertRedirect('/es/cuenta/entrar');

        $this->assertTrue(Hash::check('nueva-clave-456', $usuario->fresh()->password));
        $this->assertTrue($usuario->fresh()->hasVerifiedEmail());

        $this->post('/es/cuenta/nueva-contrasena', ['token' => $token, 'correo' => 'cliente@ejemplo.com', 'contrasena' => 'otra-clave-789', 'contrasena_confirmation' => 'otra-clave-789'])
            ->assertSessionHasErrors(['correo' => __('cuenta.enlace_no_valido')]);
    }

    /* ---------- Verificación del correo ---------- */

    public function test_el_enlace_de_verificacion_vale_sin_haber_entrado_y_une_la_ficha_de_invitado(): void
    {
        $invitado = Cliente::factory()->create(['correo' => 'nueva@ejemplo.com', 'user_id' => null, 'nombre' => 'Invitada']);
        $this->post('/en/account/register', $this->datosRegistro(['correo' => 'nueva@ejemplo.com']));
        $usuario = User::where('email', 'nueva@ejemplo.com')->sole();
        $this->assertNull($invitado->fresh()->user_id, 'Sin verificar, la ficha de invitado no se une');
        $this->post('/en/account/logout');

        $this->get($this->enlaceVerificacion($usuario, 'en'))->assertRedirect('/en/account/login')->assertSessionHas('estado');

        $this->assertTrue($usuario->fresh()->hasVerifiedEmail());
        $this->assertSame($usuario->id, $invitado->fresh()->user_id);
    }

    public function test_un_enlace_de_verificacion_manipulado_o_caducado_no_verifica(): void
    {
        $usuario = $this->cliente(['email_verified_at' => null]);

        $manipulado = str_replace(sha1('cliente@ejemplo.com'), sha1('otro@ejemplo.com'), $this->enlaceVerificacion($usuario));
        $this->get($manipulado)->assertSessionHas('error');
        $this->travel(61)->minutes();
        $this->get($this->enlaceVerificacion($usuario, minutos: -1))->assertSessionHas('error');

        $this->assertFalse($usuario->fresh()->hasVerifiedEmail());
    }

    /* ---------- Área y rutas ---------- */

    public function test_sin_sesion_mi_cuenta_lleva_a_entrar_y_con_sesion_entrar_lleva_a_mi_cuenta(): void
    {
        $this->get('/en/account')->assertRedirect('/en/account/login');

        $this->actingAs($this->cliente(['email_verified_at' => null]));
        $this->get('/es/cuenta/entrar')->assertRedirect('/es/cuenta');
        $this->get('/es/cuenta')->assertOk()->assertSee('Hola, Cliente Prueba')->assertSee('Confirma tu correo');
    }

    public function test_las_paginas_de_la_cuenta_no_se_indexan_y_la_cabecera_cambia_al_entrar(): void
    {
        $this->get('/es/cuenta/entrar')->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertSee('href="'.url('/en/account/login').'" hreflang="en"', false);
        $this->get('/es')->assertSee('href="'.url('/es/cuenta/entrar').'"', false);

        $this->actingAs($this->cliente())->get('/es')->assertSee('href="'.url('/es/cuenta').'"', false)->assertSee('Mi cuenta');
    }

    public function test_los_cambios_de_los_empleados_siguen_en_el_registro_de_actividad(): void
    {
        $empleado = $this->usuarioConRol(Rol::GestorTienda);
        $empleado->update(['name' => 'Cambio de nombre']);

        $this->assertDatabaseHas('activity_log', ['log_name' => 'usuarios', 'description' => 'Usuario modificado', 'subject_id' => $empleado->id]);
    }

    /* ---------- Revisión N3 de 5.1 y 5.2 ---------- */

    public function test_al_cambiar_la_contrasena_se_cierran_las_demas_sesiones(): void
    {
        $usuario = $this->cliente();
        $this->post('/es/cuenta/entrar', ['correo' => 'cliente@ejemplo.com', 'contrasena' => self::CLAVE]);
        $this->get('/es/cuenta')->assertOk(); // la sesión guarda la huella de la contraseña

        // Desde otro navegador, alguien restablece la contraseña.
        $usuario->forceFill(['password' => Hash::make('otra-clave-456')])->save();
        $this->app['auth']->forgetGuards(); // en producción cada petición vuelve a cargar al usuario

        $this->get('/es/cuenta')->assertRedirect('/es/cuenta/entrar');
        $this->assertGuest();
    }

    public function test_una_cuenta_desactivada_sale_aunque_tenga_la_sesion_abierta_y_no_cambia_su_contrasena(): void
    {
        $usuario = $this->cliente();
        $this->actingAs($usuario)->get('/es/cuenta')->assertOk();
        $usuario->update(['activo' => false]);

        $this->get('/es/cuenta')->assertRedirect('/es/cuenta/entrar');
        $this->assertGuest();

        $token = Password::createToken($usuario);
        $this->post('/es/cuenta/nueva-contrasena', ['token' => $token, 'correo' => 'cliente@ejemplo.com', 'contrasena' => 'nueva-clave-456', 'contrasena_confirmation' => 'nueva-clave-456'])
            ->assertSessionHasErrors('correo');
        $this->assertTrue(Hash::check(self::CLAVE, $usuario->fresh()->password));
    }

    public function test_los_correos_de_cuenta_van_por_la_cola_y_la_recuperacion_responde_igual_exista_o_no(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new VerificarCorreo);
        $this->assertInstanceOf(ShouldQueue::class, new RestablecerContrasena('x'));

        // Con el correo caído (transporte que falla), ni el registro ni la recuperación dan error.
        Notification::swap(new ChannelManager($this->app));
        Queue::fake();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);
        $this->cliente();

        $this->post('/es/cuenta/registro', $this->datosRegistro())->assertRedirect('/es/cuenta');
        $this->post('/es/cuenta/salir');
        foreach (['cliente@ejemplo.com', 'nadie@ejemplo.com'] as $correo) {
            $this->from('/es/cuenta/recuperar-contrasena')->post('/es/cuenta/recuperar-contrasena', ['correo' => $correo])
                ->assertRedirect('/es/cuenta/recuperar-contrasena');
        }
        Queue::assertPushed(SendQueuedNotifications::class, 2);
    }

    public function test_un_empleado_que_entra_por_la_tienda_queda_en_el_registro_de_accesos(): void
    {
        $empleado = $this->usuarioConRol(Rol::Administrador, ['email' => 'jefa@nga.test', 'password' => Hash::make(self::CLAVE)]);

        $this->post('/es/cuenta/entrar', ['correo' => 'jefa@nga.test', 'contrasena' => 'mal-1']);
        $this->post('/es/cuenta/entrar', ['correo' => 'jefa@nga.test', 'contrasena' => self::CLAVE]);

        $this->assertSame(1, Activity::where('log_name', 'accesos')->where('description', 'Inicio de sesión')->where('causer_id', $empleado->id)->count());
        $this->assertSame(1, Activity::where('log_name', 'accesos')->where('description', 'Intento de acceso fallido')->count());
    }

    public function test_la_baja_de_un_empleado_queda_registrada(): void
    {
        $empleado = $this->usuarioConRol(Rol::Produccion);
        $empleado->delete();

        $this->assertDatabaseHas('activity_log', ['log_name' => 'usuarios', 'description' => 'Usuario eliminado', 'subject_id' => $empleado->id]);
    }

    public function test_los_clientes_no_aparecen_en_los_usuarios_del_backend(): void
    {
        $cliente = $this->cliente();
        $this->actingAs($this->usuarioConRol());

        $this->get('/admin/usuarios')->assertOk()->assertDontSee('cliente@ejemplo.com');
        $this->get('/admin/usuarios/'.$cliente->id.'/editar')->assertNotFound();
    }

    public function test_al_unir_la_ficha_de_invitado_se_conservan_el_tipo_y_el_consentimiento_del_registro(): void
    {
        $invitado = Cliente::factory()->create(['correo' => 'nueva@ejemplo.com', 'user_id' => null]);
        $this->post('/es/cuenta/registro', $this->datosRegistro(['correo' => 'nueva@ejemplo.com', 'tipo' => 'empresa', 'marketing' => '1']));
        $usuario = User::where('email', 'nueva@ejemplo.com')->sole();

        $this->get($this->enlaceVerificacion($usuario));

        $invitado->refresh();
        $this->assertSame($usuario->id, $invitado->user_id);
        $this->assertSame('empresa', $invitado->tipo->value);
        $this->assertNotNull($invitado->marketing_consentido_en);
        $this->assertSame('registro', $invitado->marketing_origen);
        $this->assertNull($usuario->fresh()->registro_pendiente);
    }

    public static function destinos(): array
    {
        return [
            'el backend no' => ['/admin', '/es/cuenta'],
            'otro dominio no' => ['https://malo.example/es/x', '/es/cuenta'],
            'una página de la tienda sí' => ['/es/camisetas', '/es/camisetas'],
        ];
    }

    #[DataProvider('destinos')]
    public function test_tras_entrar_solo_vuelve_a_paginas_de_la_tienda(string $intento, string $destino): void
    {
        $this->cliente();
        $this->withSession(['url.intended' => str_starts_with($intento, 'http') ? $intento : url($intento)])
            ->post('/es/cuenta/entrar', ['correo' => 'cliente@ejemplo.com', 'contrasena' => self::CLAVE])
            ->assertRedirect(url($destino));
    }

    public function test_el_correo_de_un_usuario_se_guarda_en_minusculas(): void
    {
        $this->assertSame('ana@ejemplo.com', User::factory()->create(['email' => ' Ana@Ejemplo.COM '])->email);
    }

    public function test_al_verificar_en_el_mismo_navegador_pasan_a_la_ficha_los_disenos_subidos_antes(): void
    {
        Cliente::factory()->create(['correo' => 'nueva@ejemplo.com', 'user_id' => null]);
        $this->withSession(['disenos.propietario' => 'token-sesion']);
        $this->post('/es/cuenta/registro', $this->datosRegistro(['correo' => 'nueva@ejemplo.com']));
        $diseno = $this->diseno(['propietario' => hash('sha256', 'token-sesion'), 'sha256' => str_repeat('d', 64)]);
        $usuario = User::where('email', 'nueva@ejemplo.com')->sole();

        $this->get($this->enlaceVerificacion($usuario));

        $this->assertSame($usuario->fresh()->cliente->id, $diseno->fresh()->cliente_id);
    }

    public function test_tras_un_envio_con_errores_hay_un_resumen_que_recibe_el_foco(): void
    {
        $this->from('/es/cuenta/registro')->post('/es/cuenta/registro', $this->datosRegistro(['nombre' => '', 'tipo' => 'x']));

        $this->get('/es/cuenta/registro')->assertSee('id="resumen-errores"', false)->assertSee('Revisa estos 2 datos')
            ->assertSee('href="#grupo-tipo"', false)->assertSee('aria-describedby="error-tipo"', false);
    }

    /* ---------- Ayudas ---------- */

    private function datosRegistro(array $cambios = []): array
    {
        return [...['nombre' => 'Ana García', 'correo' => 'ana@ejemplo.com', 'contrasena' => self::CLAVE, 'contrasena_confirmation' => self::CLAVE, 'tipo' => 'particular'], ...$cambios];
    }

    private function cliente(array $usuario = []): User
    {
        $u = User::factory()->create([...['name' => 'Cliente Prueba', 'email' => 'cliente@ejemplo.com', 'password' => Hash::make(self::CLAVE), 'activo' => true], ...$usuario]);
        Cliente::factory()->create(['user_id' => $u->id, 'correo' => $u->email, 'nombre' => $u->name]);

        return $u->fresh();
    }

    private function diseno(array $datos): Diseno
    {
        static $n = 0;
        $n++;

        return Diseno::forceCreate([...[
            'uuid' => sprintf('00000000-0000-0000-0000-%012d', $n), 'cliente_id' => null, 'nombre_original' => "diseno-{$n}.png",
            'ruta' => "x{$n}.png", 'miniatura' => "x{$n}.webp", 'bytes' => 1, 'ancho_px' => 600, 'alto_px' => 600, 'util_x' => 0, 'util_y' => 0,
            'util_ancho_px' => 600, 'util_alto_px' => 600, 'fondo' => 'transparente', 'profundidad_bits' => 8,
            'derechos_declarados_en' => now(), 'derechos_texto' => 'Declaro…',
        ], ...$datos]);
    }

    private function enlaceVerificacion(User $usuario, string $idioma = 'es', int $minutos = 60): string
    {
        return URL::temporarySignedRoute("cuenta.{$idioma}.verificar", now()->addMinutes($minutos), ['id' => $usuario->id, 'hash' => sha1($usuario->email)]);
    }
}
