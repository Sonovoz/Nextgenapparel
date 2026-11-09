<?php

namespace Tests\Feature\Clientes;

use App\Dominio\Clientes\TipoDireccion;
use App\Dominio\Sistema\Acceso\Rol;
use App\Http\Controllers\Cuenta\BorradoController;
use App\Models\Ajuste;
use App\Models\Cliente;
use App\Models\Direccion;
use App\Models\Diseno;
use App\Models\User;
use App\Models\ZonaEnvio;
use App\Notifications\Cuenta\AcuseBorrado;
use App\Notifications\Cuenta\AvisoCambioCorreo;
use App\Notifications\Cuenta\ConfirmarNuevoCorreo;
use App\Notifications\Cuenta\SolicitudBorrado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/** Área de cliente (paso 5.3): datos, direcciones, diseños, contraseña y borrado. */
class AreaClienteTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'contrasena-segura-1';

    private User $usuario;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        [$this->usuario, $this->cliente] = $this->clienteCon('ana@ejemplo.com');
        $this->actingAs($this->usuario);
    }

    public static function paginas(): array
    {
        return [
            ['/es/cuenta', 'Hola, Ana'], ['/es/cuenta/mis-datos', 'Mis datos'], ['/es/cuenta/direcciones', 'Direcciones'],
            ['/es/cuenta/direcciones/nueva', 'Añadir una dirección'], ['/es/cuenta/mis-disenos', 'Mis diseños'], ['/es/cuenta/contrasena', 'Cambiar la contraseña'],
            ['/en/account/my-details', 'My details'], ['/en/account/addresses', 'Addresses'], ['/en/account/my-designs', 'My designs'], ['/en/account/password', 'Change your password'],
        ];
    }

    #[DataProvider('paginas')]
    public function test_cada_pagina_del_area_se_ve_en_su_idioma_y_no_se_indexa(string $url, string $texto): void
    {
        $this->get($url)->assertOk()->assertSee($texto)->assertSee('noindex, nofollow', false)->assertSee('aria-current="page"', false);
    }

    /* ---------- Mis datos ---------- */

    public function test_guarda_los_datos_y_el_consentimiento_con_fecha_y_origen(): void
    {
        $this->put('/es/cuenta/mis-datos', $this->datos(['tipo' => 'autonomo', 'nif' => 'x-1234567-l', 'telefono' => '+34 600 000 000', 'idioma' => 'en', 'marketing' => '1']))
            ->assertRedirect('/es/cuenta/mis-datos')->assertSessionHas('estado', __('cuenta.datos_guardados'));

        $this->cliente->refresh();
        $this->assertSame(['autonomo', 'X1234567L', '+34 600 000 000', 'en', 'area_cliente'], [$this->cliente->tipo->value, $this->cliente->nif, $this->cliente->telefono, $this->cliente->idioma, $this->cliente->marketing_origen]);
        $this->assertNotNull($this->cliente->marketing_consentido_en);

        $this->put('/es/cuenta/mis-datos', $this->datos(['tipo' => 'autonomo']));
        $this->assertNull($this->cliente->fresh()->marketing_consentido_en, 'Al desmarcar se retira el consentimiento');
    }

    public function test_el_recargo_de_equivalencia_no_lo_cambia_el_cliente_y_un_particular_no_lo_tiene(): void
    {
        $this->cliente->update(['tipo' => 'empresa', 'recargo_equivalencia' => true]);

        $this->put('/es/cuenta/mis-datos', $this->datos(['tipo' => 'empresa', 'recargo_equivalencia' => '0']));
        $this->assertTrue($this->cliente->fresh()->recargo_equivalencia);

        $this->put('/es/cuenta/mis-datos', $this->datos(['tipo' => 'particular']));
        $this->assertFalse($this->cliente->fresh()->recargo_equivalencia);
    }

    public static function datosNoValidos(): array
    {
        return [
            'NIF con letra equivocada' => [['nif' => '12345678A'], 'nif'],
            'teléfono con letras' => [['telefono' => 'llámame'], 'telefono'],
            'idioma inventado' => [['idioma' => 'fr'], 'idioma'],
            'cambiar el correo sin la contraseña' => [['correo' => 'otra@ejemplo.com'], 'contrasena_actual'],
            'cambiar a un correo de otro cliente' => [['correo' => 'luis@ejemplo.com', 'contrasena_actual' => self::CLAVE], 'correo'],
        ];
    }

    #[DataProvider('datosNoValidos')]
    public function test_rechaza_datos_no_validos(array $cambios, string $campo): void
    {
        $this->clienteCon('luis@ejemplo.com');

        $this->from('/es/cuenta/mis-datos')->put('/es/cuenta/mis-datos', $this->datos($cambios))->assertSessionHasErrors($campo);
        $this->assertSame('ana@ejemplo.com', $this->usuario->fresh()->email);
    }

    public function test_el_correo_solo_cambia_al_abrir_el_enlace_que_llega_al_nuevo_y_se_avisa_al_anterior(): void
    {
        $this->put('/es/cuenta/mis-datos', $this->datos(['correo' => 'Ana.Nueva@Ejemplo.com', 'contrasena_actual' => self::CLAVE]))
            ->assertSessionHas('estado', __('cuenta.correo_cambiado'));

        // Hasta confirmar, todo sigue con el correo de siempre (revisión N3 del 5.3, A1).
        $this->assertSame('ana@ejemplo.com', $this->usuario->fresh()->email);
        $this->assertSame('ana@ejemplo.com', $this->cliente->fresh()->correo);
        $this->assertSame('ana.nueva@ejemplo.com', $this->usuario->fresh()->correo_pendiente);
        $enlace = null;
        Notification::assertSentOnDemand(ConfirmarNuevoCorreo::class, function (ConfirmarNuevoCorreo $n, array $canales, object $destino) use (&$enlace) {
            $enlace = $n->toMail($destino)->actionUrl;

            return $destino->routes['mail'] === 'ana.nueva@ejemplo.com';
        });
        Notification::assertSentTo($this->usuario, AvisoCambioCorreo::class);

        $manipulado = str_replace(sha1('ana.nueva@ejemplo.com'), sha1('otra@ejemplo.com'), $enlace);
        $this->get($manipulado)->assertSessionHas('error');
        $this->assertSame('ana@ejemplo.com', $this->usuario->fresh()->email);

        $this->get($enlace)->assertRedirect('/es/cuenta/mis-datos')->assertSessionHas('estado');
        $this->assertSame('ana.nueva@ejemplo.com', $this->usuario->fresh()->email);
        $this->assertSame('ana.nueva@ejemplo.com', $this->cliente->fresh()->correo);
        $this->assertNull($this->usuario->fresh()->correo_pendiente);
        $this->assertTrue($this->usuario->fresh()->hasVerifiedEmail());
    }

    public function test_no_se_puede_pedir_un_correo_de_una_ficha_de_invitado_ni_uno_pendiente_de_otro(): void
    {
        Cliente::factory()->create(['correo' => 'invitada@ejemplo.com', 'user_id' => null]);
        [$otro] = $this->clienteCon('luis@ejemplo.com');
        $otro->forceFill(['correo_pendiente' => 'reservado@ejemplo.com'])->save();

        foreach (['invitada@ejemplo.com', 'reservado@ejemplo.com'] as $correo) {
            $this->put('/es/cuenta/mis-datos', $this->datos(['correo' => $correo, 'contrasena_actual' => self::CLAVE]))
                ->assertSessionHasErrors(['correo' => __('cuenta.correo_no_disponible')]);
        }
    }

    public function test_tras_un_error_la_casilla_de_marketing_respeta_lo_que_envio_el_cliente(): void
    {
        $this->cliente->update(['marketing_consentido_en' => now(), 'marketing_origen' => 'registro']);

        // Desmarca y envía con un NIF erróneo: la casilla no puede volver marcada (A2).
        $this->from('/es/cuenta/mis-datos')->put('/es/cuenta/mis-datos', $this->datos(['nif' => '12345678A']));
        $html = $this->get('/es/cuenta/mis-datos')->getContent();

        $this->assertMatchesRegularExpression('/<input type="checkbox" name="marketing" value="1"\s*>/', $html);
    }

    public function test_cambiar_el_nif_o_el_tipo_retira_el_recargo_y_queda_registrado(): void
    {
        $this->cliente->update(['tipo' => 'empresa', 'nif' => 'B12345674', 'recargo_equivalencia' => true]);

        $this->put('/es/cuenta/mis-datos', $this->datos(['tipo' => 'empresa', 'nif' => 'A58818501']));

        $this->assertFalse($this->cliente->fresh()->recargo_equivalencia);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'clientes', 'description' => 'Datos fiscales cambiados por el cliente: recargo de equivalencia retirado', 'subject_id' => $this->cliente->id]);
    }

    public function test_sin_telefono_en_la_peticion_no_hay_error(): void
    {
        $datos = $this->datos();
        unset($datos['telefono'], $datos['nif']);

        $this->put('/es/cuenta/mis-datos', $datos)->assertRedirect('/es/cuenta/mis-datos')->assertSessionHasNoErrors();
    }

    public function test_comprobar_la_contrasena_en_mis_datos_tiene_limite_de_intentos(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->put('/es/cuenta/mis-datos', $this->datos(['correo' => 'otra@ejemplo.com', 'contrasena_actual' => 'mal-'.$i]));
        }

        $this->from('/es/cuenta/mis-datos')->put('/es/cuenta/mis-datos', $this->datos(['correo' => 'otra@ejemplo.com', 'contrasena_actual' => self::CLAVE]))
            ->assertSessionHas('error');
        $this->assertNull($this->usuario->fresh()->correo_pendiente);
    }

    /* ---------- Direcciones ---------- */

    public function test_la_primera_de_cada_tipo_es_la_predeterminada_y_solo_hay_una(): void
    {
        $this->post('/es/cuenta/direcciones', $this->direccion(['direccion' => 'Calle A 1']))->assertRedirect('/es/cuenta/direcciones');
        $this->post('/es/cuenta/direcciones', $this->direccion(['direccion' => 'Calle B 2']));
        $this->post('/es/cuenta/direcciones', $this->direccion(['direccion' => 'Calle C 3', 'predeterminada' => '1']));
        $this->post('/es/cuenta/direcciones', $this->direccion(['direccion' => 'Factura 9', 'tipo' => 'facturacion']));

        $this->assertSame(['Calle C 3', 'Factura 9'], $this->cliente->direcciones()->where('predeterminada', true)->orderBy('id')->pluck('direccion')->all());

        $c = $this->cliente->direcciones()->where('direccion', 'Calle C 3')->sole();
        $this->delete('/es/cuenta/direcciones/'.$c->uuid)->assertRedirect('/es/cuenta/direcciones');
        $this->assertSame('Calle B 2', $this->cliente->direcciones()->where('tipo', 'envio')->where('predeterminada', true)->sole()->direccion);

        $a = $this->cliente->direcciones()->where('direccion', 'Calle A 1')->sole();
        $this->post('/es/cuenta/direcciones/'.$a->uuid.'/predeterminada');
        $this->assertTrue($a->fresh()->predeterminada);
        $this->assertSame(1, $this->cliente->direcciones()->where('tipo', 'envio')->where('predeterminada', true)->count());
    }

    public function test_al_cambiar_de_tipo_el_anterior_sigue_teniendo_predeterminada(): void
    {
        $this->post('/es/cuenta/direcciones', $this->direccion(['direccion' => 'Calle A 1']));
        $this->post('/es/cuenta/direcciones', $this->direccion(['direccion' => 'Calle B 2']));
        $a = $this->cliente->direcciones()->where('direccion', 'Calle A 1')->sole();

        $this->put('/es/cuenta/direcciones/'.$a->uuid, $this->direccion(['direccion' => 'Calle A 1', 'tipo' => 'facturacion']));

        $this->assertTrue($a->fresh()->predeterminada);
        $this->assertSame('Calle B 2', $this->cliente->direcciones()->where('tipo', 'envio')->where('predeterminada', true)->sole()->direccion);
    }

    public static function direccionesNoValidas(): array
    {
        return [
            'CP español que no existe' => [['codigo_postal' => '60000'], 'codigo_postal'],
            'CP corto' => [['codigo_postal' => '2801'], 'codigo_postal'],
            'país sin zona de envío' => [['pais' => 'US', 'codigo_postal' => '10001'], 'pais'],
            'sin dirección' => [['direccion' => ''], 'direccion'],
        ];
    }

    #[DataProvider('direccionesNoValidas')]
    public function test_rechaza_direcciones_no_validas(array $cambios, string $campo): void
    {
        $this->post('/es/cuenta/direcciones', $this->direccion($cambios))->assertSessionHasErrors($campo);
        $this->assertSame(0, $this->cliente->direcciones()->count());
    }

    public function test_los_paises_salen_de_las_zonas_de_envio_y_el_cp_se_valida_por_pais(): void
    {
        ZonaEnvio::create(['nombre' => ['es' => 'Portugal', 'en' => 'Portugal'], 'paises' => ['PT'], 'metodo' => 'por_caja', 'precio_caja' => 1000, 'activa' => true]);

        $this->get('/es/cuenta/direcciones/nueva')->assertSee('value="PT"', false)->assertSee('Portugal');
        $this->post('/es/cuenta/direcciones', $this->direccion(['pais' => 'PT', 'codigo_postal' => '28013']))->assertSessionHasErrors('codigo_postal');
        $this->post('/es/cuenta/direcciones', $this->direccion(['pais' => 'PT', 'codigo_postal' => '1000-001']))->assertSessionHasNoErrors();
    }

    public function test_las_direcciones_de_envio_tienen_que_caer_en_una_zona_de_envio(): void
    {
        ZonaEnvio::create(['nombre' => ['es' => 'Madrid', 'en' => 'Madrid'], 'paises' => ['ES'], 'prefijos_cp' => ['28'], 'metodo' => 'por_caja', 'precio_caja' => 500, 'activa' => true]);

        $this->post('/es/cuenta/direcciones', $this->direccion(['codigo_postal' => '07001']))->assertSessionHasErrors(['codigo_postal' => __('cuenta.cp_sin_envio')]);
        $this->post('/es/cuenta/direcciones', $this->direccion(['codigo_postal' => '28013']))->assertSessionHasNoErrors();
        // La de facturación puede estar fuera de las zonas de envío.
        $this->post('/es/cuenta/direcciones', $this->direccion(['tipo' => 'facturacion', 'codigo_postal' => '07001']))->assertSessionHasNoErrors();
    }

    /* ---------- Acceso entre clientes ---------- */

    public function test_nadie_ve_ni_toca_las_direcciones_ni_los_disenos_de_otro_cliente(): void
    {
        [, $otro] = $this->clienteCon('luis@ejemplo.com');
        $suya = Direccion::factory()->for($otro)->create(['direccion' => 'Calle Ajena 99']);
        $diseno = $this->diseno($otro);

        $this->get('/es/cuenta/direcciones/'.$suya->uuid.'/editar')->assertNotFound();
        $this->put('/es/cuenta/direcciones/'.$suya->uuid, $this->direccion())->assertNotFound();
        $this->post('/es/cuenta/direcciones/'.$suya->uuid.'/predeterminada')->assertNotFound();
        $this->delete('/es/cuenta/direcciones/'.$suya->uuid)->assertNotFound();
        $this->delete('/es/cuenta/mis-disenos/'.$diseno->uuid)->assertNotFound();
        $this->get('/es/cuenta/direcciones')->assertDontSee('Calle Ajena 99');
        $this->get('/es/cuenta/mis-disenos')->assertDontSee($diseno->nombre_original);

        $this->assertModelExists($suya);
        $this->assertModelExists($diseno);
    }

    public function test_borra_sus_disenos(): void
    {
        $diseno = $this->diseno($this->cliente);

        $this->get('/es/cuenta/mis-disenos')->assertSee($diseno->nombre_original);
        $this->delete('/es/cuenta/mis-disenos/'.$diseno->uuid)->assertRedirect('/es/cuenta/mis-disenos');

        $this->assertModelMissing($diseno);
    }

    /* ---------- Contraseña y borrado ---------- */

    public function test_cambiar_la_contrasena_pide_la_actual_y_sigue_dentro(): void
    {
        $this->put('/es/cuenta/contrasena', ['contrasena_actual' => 'mal-123456', 'contrasena' => 'nueva-clave-456', 'contrasena_confirmation' => 'nueva-clave-456'])
            ->assertSessionHasErrors(['contrasena_actual' => __('cuenta.contrasena_actual_mal')]);

        $this->put('/es/cuenta/contrasena', ['contrasena_actual' => self::CLAVE, 'contrasena' => 'nueva-clave-456', 'contrasena_confirmation' => 'nueva-clave-456'])
            ->assertRedirect('/es/cuenta/contrasena');

        $this->assertTrue(Hash::check('nueva-clave-456', $this->usuario->fresh()->password));
        $this->get('/es/cuenta')->assertOk();
    }

    public function test_el_borrado_se_solicita_con_la_contrasena_queda_registrado_y_avisa_a_la_tienda_una_vez(): void
    {
        Ajuste::guardar('tienda.correo_contacto', 'tienda@nga.test');

        $this->post('/es/cuenta/borrar-cuenta', ['contrasena_borrado' => 'mal-123456'])->assertSessionHasErrors('contrasena_borrado');
        $this->assertNull($this->cliente->fresh()->borrado_solicitado_en);

        $this->post('/es/cuenta/borrar-cuenta', ['contrasena_borrado' => self::CLAVE])->assertRedirect('/es/cuenta/mis-datos');
        $this->post('/es/cuenta/borrar-cuenta', ['contrasena_borrado' => self::CLAVE]);

        $this->assertNotNull($this->cliente->fresh()->borrado_solicitado_en);
        $this->assertSame(1, Activity::where('log_name', 'clientes')->count());
        $this->assertDatabaseHas('activity_log', ['log_name' => 'clientes', 'description' => 'Borrado de cuenta solicitado', 'subject_id' => $this->cliente->id]);
        Notification::assertSentOnDemandTimes(SolicitudBorrado::class, 1);
        // La ficha se vuelve a leer de la base de datos, como en producción (antes daba error 500).
        $this->actingAs($this->usuario->fresh())->get('/es/cuenta/mis-datos')->assertOk()->assertSee('Hemos recibido tu solicitud de borrado');
    }

    public function test_sin_correo_de_contacto_el_aviso_de_borrado_va_al_de_alertas_o_a_los_administradores_y_el_cliente_recibe_acuse(): void
    {
        config(['errores.correo_alertas' => 'alertas@nga.test']);
        $this->assertSame(['alertas@nga.test'], BorradoController::destinatarios());

        config(['errores.correo_alertas' => null]);
        $admin = $this->usuarioConRol(Rol::Administrador, ['email' => 'jefa@nga.test']);
        $this->usuarioConRol(Rol::Produccion, ['email' => 'taller@nga.test']);
        $this->assertSame([$admin->email], BorradoController::destinatarios());

        $this->post('/es/cuenta/borrar-cuenta', ['contrasena_borrado' => self::CLAVE]);
        Notification::assertSentOnDemand(SolicitudBorrado::class, fn ($n, $c, $destino) => $destino->routes['mail'] === 'jefa@nga.test');
        Notification::assertSentTo($this->usuario, AcuseBorrado::class);
    }

    public function test_tras_una_contrasena_erronea_el_formulario_de_borrado_sigue_abierto(): void
    {
        $this->from('/es/cuenta/mis-datos')->post('/es/cuenta/borrar-cuenta', ['contrasena_borrado' => 'mal-123456']);

        $this->get('/es/cuenta/mis-datos')->assertSee('<details class="borrado-detalle"  open', false);
    }

    public function test_sin_ficha_de_cliente_el_area_pide_confirmar_el_correo(): void
    {
        Cliente::factory()->create(['correo' => 'pendiente@ejemplo.com', 'user_id' => null]);
        $sinFicha = User::factory()->create(['email' => 'pendiente@ejemplo.com', 'email_verified_at' => null]);

        $this->actingAs($sinFicha)->get('/es/cuenta/mis-datos')->assertRedirect('/es/cuenta')->assertSessionHas('error', __('cuenta.necesita_ficha'));
    }

    /* ---------- Ayudas ---------- */

    /** @return array{0: User, 1: Cliente} */
    private function clienteCon(string $correo): array
    {
        $usuario = User::factory()->create(['name' => 'Ana', 'email' => $correo, 'password' => Hash::make(self::CLAVE)]);
        $cliente = Cliente::factory()->create(['user_id' => $usuario->id, 'correo' => $correo, 'nombre' => 'Ana']);

        return [$usuario, $cliente];
    }

    private function datos(array $cambios = []): array
    {
        return [...['nombre' => 'Ana García', 'correo' => 'ana@ejemplo.com', 'tipo' => 'particular', 'idioma' => 'es', 'nif' => '', 'telefono' => ''], ...$cambios];
    }

    private function direccion(array $cambios = []): array
    {
        return [...['tipo' => TipoDireccion::Envio->value, 'nombre' => 'Ana García', 'direccion' => 'Calle Mayor 1', 'codigo_postal' => '28013', 'localidad' => 'Madrid', 'pais' => 'ES'], ...$cambios];
    }

    private function diseno(Cliente $cliente): Diseno
    {
        static $n = 0;
        $n++;

        return Diseno::forceCreate([
            'uuid' => sprintf('00000000-0000-0000-0000-%012d', $n), 'cliente_id' => $cliente->id, 'nombre_original' => "logo-{$n}.png",
            'ruta' => "x{$n}.png", 'miniatura' => "x{$n}.webp", 'bytes' => 1, 'sha256' => str_repeat((string) $n, 64), 'ancho_px' => 600, 'alto_px' => 600,
            'util_x' => 0, 'util_y' => 0, 'util_ancho_px' => 600, 'util_alto_px' => 600, 'fondo' => 'transparente', 'profundidad_bits' => 8,
            'derechos_declarados_en' => now(), 'derechos_texto' => 'Declaro…',
        ]);
    }
}
