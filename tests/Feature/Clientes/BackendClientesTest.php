<?php

namespace Tests\Feature\Clientes;

use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Pages\AjustesGenerales;
use App\Filament\Resources\Clientes\ClienteResource;
use App\Filament\Resources\Clientes\Pages\EditCliente;
use App\Filament\Resources\Clientes\Pages\ListClientes;
use App\Filament\Resources\Clientes\RelationManagers\DireccionesRelationManager;
use App\Models\Ajuste;
use App\Models\Cliente;
use App\Models\Direccion;
use App\Models\Diseno;
use App\Models\User;
use App\Models\ZonaEnvio;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/** Backend → Clientes (paso 5.4). */
class BackendClientesTest extends TestCase
{
    use RefreshDatabase;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        $usuario = User::factory()->create(['email' => 'ana@ejemplo.com', 'password' => Hash::make('contrasena-segura-1')]);
        $this->cliente = Cliente::factory()->create(['user_id' => $usuario->id, 'correo' => 'ana@ejemplo.com', 'nombre' => 'Ana García', 'tipo' => 'empresa', 'nif' => 'B12345674']);
    }

    public static function roles(): array
    {
        return [
            'superadministrador' => [Rol::Superadministrador, 200],
            'administrador' => [Rol::Administrador, 200],
            'gestor de la tienda' => [Rol::GestorTienda, 200],
            'producción' => [Rol::Produccion, 403],
        ];
    }

    #[DataProvider('roles')]
    public function test_la_seccion_clientes_es_para_quien_tiene_su_permiso(Rol $rol, int $estado): void
    {
        $this->actingAs($this->usuarioConRol($rol));

        $this->get(ClienteResource::getUrl('index'))->assertStatus($estado);
        $this->get(ClienteResource::getUrl('edit', ['record' => $this->cliente]))->assertStatus($estado);
    }

    public function test_la_lista_muestra_y_busca_clientes_y_marca_las_solicitudes_de_borrado(): void
    {
        $otro = Cliente::factory()->create(['nombre' => 'Luis Pérez', 'borrado_solicitado_en' => now()]);
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));

        Livewire::test(ListClientes::class)->assertCanSeeTableRecords([$this->cliente, $otro])
            ->searchTable('B12345674')->assertCanSeeTableRecords([$this->cliente])->assertCanNotSeeTableRecords([$otro])
            ->searchTable('')->filterTable('borrado_solicitado_en', true)->assertCanSeeTableRecords([$otro])->assertCanNotSeeTableRecords([$this->cliente]);
        $this->assertSame('1', ClienteResource::getNavigationBadge());
    }

    public function test_el_equipo_activa_el_recargo_y_el_cambio_queda_registrado_con_antes_y_despues(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));

        Livewire::test(EditCliente::class, ['record' => $this->cliente->getRouteKey()])
            ->fillForm(['recargo_equivalencia' => true, 'nif' => 'a-5881850-1'])->call('save')->assertHasNoFormErrors();

        $this->cliente->refresh();
        $this->assertTrue($this->cliente->recargo_equivalencia);
        $this->assertSame('A58818501', $this->cliente->nif);
        $actividad = Activity::where('log_name', 'clientes')->where('description', 'Cliente modificado')->sole();
        $this->assertSame(['nif' => 'B12345674', 'recargo_equivalencia' => false], $actividad->properties['old']);
        $this->assertSame(['nif' => 'A58818501', 'recargo_equivalencia' => true], $actividad->properties['attributes']);
    }

    public function test_un_nif_no_valido_no_se_guarda_y_un_particular_no_tiene_recargo(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));

        Livewire::test(EditCliente::class, ['record' => $this->cliente->getRouteKey()])
            ->fillForm(['nif' => '12345678A'])->call('save')->assertHasFormErrors(['nif']);

        $this->cliente->update(['recargo_equivalencia' => true]);
        Livewire::test(EditCliente::class, ['record' => $this->cliente->getRouteKey()])
            ->fillForm(['tipo' => 'particular'])->call('save')->assertHasNoFormErrors();
        $this->assertFalse($this->cliente->fresh()->recargo_equivalencia);
    }

    public function test_desactivar_al_cliente_desactiva_su_cuenta_y_le_saca_de_la_tienda(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));
        Livewire::test(EditCliente::class, ['record' => $this->cliente->getRouteKey()])->fillForm(['activo' => false])->call('save');

        $this->assertFalse($this->cliente->usuario->fresh()->activo);
        $this->actingAs($this->cliente->usuario->fresh())->get('/es/cuenta')->assertRedirect('/es/cuenta/entrar');
    }

    public function test_el_equipo_gestiona_las_direcciones_con_las_mismas_reglas_y_queda_registrado(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));
        $datos = ['tipo' => 'envio', 'nombre' => 'Ana', 'direccion' => 'Calle Mayor 1', 'codigo_postal' => '28013', 'localidad' => 'Madrid', 'pais' => 'ES'];

        Livewire::test(DireccionesRelationManager::class, ['ownerRecord' => $this->cliente, 'pageClass' => EditCliente::class])
            ->callTableAction('create', data: $datos)->assertHasNoTableActionErrors()
            ->callTableAction('create', data: [...$datos, 'direccion' => 'Calle Otra 2'])
            ->callTableAction('create', data: [...$datos, 'codigo_postal' => '60000'])->assertHasTableActionErrors(['codigo_postal']);

        $this->assertSame(['Calle Mayor 1'], $this->cliente->direcciones()->where('predeterminada', true)->pluck('direccion')->all());
        $this->assertSame(2, Activity::where('description', 'Dirección añadida por el equipo')->count());
    }

    public function test_borrar_el_cliente_borra_cuenta_ficha_direcciones_y_disenos_con_sus_archivos(): void
    {
        Storage::fake('disenos');
        Storage::disk('disenos')->put('2026/11/logo.png', 'png');
        Storage::disk('disenos')->put('2026/11/logo-mini.webp', 'webp');
        $direccion = Direccion::factory()->for($this->cliente)->create();
        $diseno = Diseno::forceCreate([
            'uuid' => '00000000-0000-0000-0000-000000000001', 'cliente_id' => $this->cliente->id, 'nombre_original' => 'logo.png', 'ruta' => '2026/11/logo.png',
            'miniatura' => '2026/11/logo-mini.webp', 'bytes' => 3, 'sha256' => str_repeat('a', 64), 'ancho_px' => 1, 'alto_px' => 1, 'util_x' => 0, 'util_y' => 0,
            'util_ancho_px' => 1, 'util_alto_px' => 1, 'fondo' => 'transparente', 'profundidad_bits' => 8, 'derechos_declarados_en' => now(), 'derechos_texto' => 'x',
        ]);
        $usuario = $this->cliente->usuario;
        $this->actingAs($this->usuarioConRol(Rol::Administrador));

        Livewire::test(EditCliente::class, ['record' => $this->cliente->getRouteKey()])
            ->callAction('borrar', data: ['confirmacion' => 'borrar'])->assertHasActionErrors(['confirmacion']);
        $this->assertModelExists($this->cliente);

        Livewire::test(EditCliente::class, ['record' => $this->cliente->getRouteKey()])
            ->callAction('borrar', data: ['confirmacion' => 'BORRAR'])->assertRedirect(ClienteResource::getUrl('index'));

        $this->assertModelMissing($this->cliente);
        $this->assertModelMissing($usuario);
        $this->assertModelMissing($direccion);
        $this->assertModelMissing($diseno);
        $this->assertSame([], Storage::disk('disenos')->allFiles());
        $registro = Activity::where('description', 'Cliente borrado')->sole();
        $this->assertStringNotContainsString('ana@ejemplo.com', json_encode($registro->properties));
    }

    public function test_borrar_la_ficha_de_un_empleado_que_compraba_no_borra_su_usuario(): void
    {
        $empleado = $this->usuarioConRol(Rol::GestorTienda);
        $ficha = Cliente::factory()->create(['user_id' => $empleado->id, 'correo' => $empleado->email]);
        $this->actingAs($this->usuarioConRol(Rol::Administrador));

        Livewire::test(EditCliente::class, ['record' => $ficha->getRouteKey()])->callAction('borrar', data: ['confirmacion' => 'BORRAR']);

        $this->assertModelMissing($ficha);
        $this->assertModelExists($empleado);
    }

    public function test_se_puede_anular_una_solicitud_de_borrado(): void
    {
        $this->cliente->update(['borrado_solicitado_en' => now()]);
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));

        Livewire::test(EditCliente::class, ['record' => $this->cliente->getRouteKey()])->callAction('anular_borrado');

        $this->assertNull($this->cliente->fresh()->borrado_solicitado_en);
        $this->assertDatabaseHas('activity_log', ['description' => 'Solicitud de borrado anulada']);
    }

    /* ---------- Revisión N3 del 5.4 ---------- */

    public function test_pasar_a_particular_y_desactivar_a_la_vez_desactiva_la_cuenta_y_registra_la_retirada_del_recargo(): void
    {
        $this->cliente->update(['recargo_equivalencia' => true]);
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));

        Livewire::test(EditCliente::class, ['record' => $this->cliente->getRouteKey()])
            ->fillForm(['tipo' => 'particular', 'activo' => false])->call('save')->assertHasNoFormErrors();

        $this->assertFalse($this->cliente->fresh()->recargo_equivalencia);
        $this->assertFalse($this->cliente->usuario->fresh()->activo);
        $apunte = Activity::where('description', 'Cliente modificado')->sole();
        $this->assertTrue($apunte->properties['old']['recargo_equivalencia']);
        $this->assertFalse($apunte->properties['attributes']['recargo_equivalencia']);
    }

    public function test_tras_borrar_no_queda_ningun_dato_personal_del_cliente(): void
    {
        $usuario = $this->cliente->usuario;
        // Rastro previo: cambios del equipo y del cliente, un token de contraseña y una sesión.
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));
        Livewire::test(EditCliente::class, ['record' => $this->cliente->getRouteKey()])->fillForm(['telefono' => '600111222'])->call('save');
        $this->actingAs($usuario)->put('/es/cuenta/mis-datos', $this->datos(['nif' => 'A58818501', 'telefono' => '600111222']));
        Password::createToken($usuario);
        DB::table('sessions')->insert(['id' => 'sesion-de-ana', 'user_id' => $usuario->id, 'ip_address' => '10.0.0.1', 'user_agent' => 'Navegador', 'payload' => '', 'last_activity' => time()]);
        $this->assertTrue($this->hayRastro(), 'Antes de borrar hay datos en el registro');

        $this->actingAs($this->usuarioConRol(Rol::Administrador));
        Livewire::test(EditCliente::class, ['record' => $this->cliente->getRouteKey()])->callAction('borrar', data: ['confirmacion' => 'BORRAR']);

        $this->assertFalse($this->hayRastro());
        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', 'ana@ejemplo.com')->count());
        $this->assertSame(0, DB::table('sessions')->where('id', 'sesion-de-ana')->count());
        $this->assertDatabaseHas('activity_log', ['description' => 'Cliente borrado']);
    }

    public function test_el_backend_no_acepta_direcciones_de_envio_fuera_de_las_zonas(): void
    {
        ZonaEnvio::create(['nombre' => ['es' => 'Baleares', 'en' => 'Balearic'], 'paises' => ['ES'], 'prefijos_cp' => ['07'], 'metodo' => 'por_caja', 'precio_caja' => 900, 'activa' => true]);
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));
        $datos = ['tipo' => 'envio', 'nombre' => 'Ana', 'direccion' => 'Calle Mayor 1', 'codigo_postal' => '28013', 'localidad' => 'Madrid', 'pais' => 'ES'];

        $gestor = fn () => Livewire::test(DireccionesRelationManager::class, ['ownerRecord' => $this->cliente, 'pageClass' => EditCliente::class]);
        $gestor()->callTableAction('create', data: $datos)->assertHasTableActionErrors(['codigo_postal']);
        $gestor()->callTableAction('create', data: [...$datos, 'codigo_postal' => '07001', 'telefono' => 'llámame'])->assertHasTableActionErrors(['telefono']);
        $this->assertSame(0, $this->cliente->direcciones()->count());
    }

    public function test_fijar_la_direccion_predeterminada_queda_registrado(): void
    {
        Direccion::factory()->for($this->cliente)->create(['predeterminada' => true]);
        $segunda = Direccion::factory()->for($this->cliente)->create();
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));

        Livewire::test(DireccionesRelationManager::class, ['ownerRecord' => $this->cliente, 'pageClass' => EditCliente::class])->callTableAction('predeterminar', $segunda);

        $this->assertTrue($segunda->fresh()->predeterminada);
        $this->assertDatabaseHas('activity_log', ['description' => 'Dirección predeterminada cambiada por el equipo']);
    }

    public function test_una_ficha_desactivada_no_puede_usar_el_area_aunque_su_cuenta_entre(): void
    {
        $this->cliente->update(['activo' => false]);

        $this->actingAs($this->cliente->usuario)->get('/es/cuenta/mis-datos')->assertRedirect('/es/cuenta')->assertSessionHas('error', __('cuenta.ficha_inactiva'));
    }

    public static function modosRecargo(): array
    {
        return ['lo indica el equipo' => ['equipo'], 'lo indica el cliente' => ['cliente']];
    }

    #[DataProvider('modosRecargo')]
    public function test_pasar_de_empresa_con_recargo_a_particular_queda_registrado_en_los_dos_modos(string $modo): void
    {
        Ajuste::guardar('clientes.recargo_lo_indica', $modo);
        $this->cliente->update(['recargo_equivalencia' => true]);

        $this->actingAs($this->cliente->usuario)->put('/es/cuenta/mis-datos', $this->datos(['tipo' => 'particular', 'recargo_equivalencia' => '1']));

        $this->assertFalse($this->cliente->fresh()->recargo_equivalencia);
        $this->assertDatabaseHas('activity_log', ['description' => 'Datos fiscales cambiados por el cliente: recargo de equivalencia retirado', 'subject_id' => $this->cliente->id]);
    }

    /* ---------- Quién indica el recargo (Ajustes → General) ---------- */

    public function test_por_defecto_lo_indica_el_equipo_y_el_cliente_no_puede_cambiarlo(): void
    {
        $this->actingAs($this->cliente->usuario)->put('/es/cuenta/mis-datos', $this->datos(['recargo_equivalencia' => '1']));

        $this->assertFalse($this->cliente->fresh()->recargo_equivalencia);
        $this->get('/es/cuenta/mis-datos')->assertDontSee('name="recargo_equivalencia"', false);
    }

    public function test_si_lo_indica_el_cliente_lo_marca_en_mis_datos_y_queda_registrado(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::Administrador));
        Livewire::test(AjustesGenerales::class)->fillForm(['recargo_lo_indica' => 'cliente'])->call('callMountedAction')->callAction('guardar');
        $this->assertSame('cliente', Ajuste::obtener('clientes.recargo_lo_indica'));

        $this->actingAs($this->cliente->usuario)->get('/es/cuenta/mis-datos')->assertSee('name="recargo_equivalencia"', false);
        $this->put('/es/cuenta/mis-datos', $this->datos(['recargo_equivalencia' => '1']));
        $this->assertTrue($this->cliente->fresh()->recargo_equivalencia);
        $this->assertDatabaseHas('activity_log', ['description' => 'Recargo de equivalencia activado por el cliente']);

        // Un particular, ni aunque lo envíe.
        $this->put('/es/cuenta/mis-datos', $this->datos(['tipo' => 'particular', 'recargo_equivalencia' => '1']));
        $this->assertFalse($this->cliente->fresh()->recargo_equivalencia);
    }

    private function hayRastro(): bool
    {
        $todo = json_encode(Activity::all()->map(fn ($a) => [$a->description, $a->properties])->all(), JSON_UNESCAPED_UNICODE);

        return collect(['ana@ejemplo.com', 'Ana García', 'B12345674', 'A58818501', '600111222'])->contains(fn ($t) => str_contains($todo, $t));
    }

    private function datos(array $cambios = []): array
    {
        return [...['nombre' => 'Ana García', 'correo' => 'ana@ejemplo.com', 'tipo' => 'empresa', 'idioma' => 'es', 'nif' => 'B12345674', 'telefono' => ''], ...$cambios];
    }
}
