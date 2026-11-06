<?php

namespace Tests\Feature\Sistema;

use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Resources\Actividades\Pages\ListActividades;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class RegistroActividadTest extends TestCase
{
    use RefreshDatabase;

    private User $superadministrador;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->superadministrador = $this->usuarioConRol(atributos: ['name' => 'Ana']);
        Activity::query()->delete();
        $this->actingAs($this->superadministrador);
    }

    public function test_crear_un_usuario_queda_registrado_con_su_rol_y_sin_contrasena(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Marta',
                'email' => 'marta@ejemplo.es',
                'rol' => Rol::Produccion->value,
                'activo' => true,
                'password' => 'clave-de-marta-123',
                'password_confirmation' => 'clave-de-marta-123',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(['Usuario creado', 'Rol asignado'], Activity::orderBy('id')->pluck('description')->all());

        foreach (Activity::all() as $actividad) {
            $this->assertTrue($actividad->causer->is($this->superadministrador));
            $this->assertStringNotContainsString('clave-de-marta', json_encode($actividad->properties));
            $this->assertArrayNotHasKey('password', (array) ($actividad->properties['attributes'] ?? []));
        }
    }

    public function test_cambiar_el_rol_y_desactivar_registra_antes_y_despues(): void
    {
        $luis = $this->usuarioConRol(Rol::GestorTienda, ['name' => 'Luis']);
        Activity::query()->delete();

        Livewire::test(EditUser::class, ['record' => $luis->getRouteKey()])
            ->fillForm(['rol' => Rol::Administrador->value, 'activo' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $rol = Activity::where('description', 'Rol cambiado')->sole();
        $this->assertSame('Gestor de la tienda', $rol->properties['old']['rol']);
        $this->assertSame('Administrador', $rol->properties['attributes']['rol']);

        $cambio = Activity::where('description', 'Usuario modificado')->sole();
        $this->assertTrue($cambio->properties['old']['activo']);
        $this->assertFalse($cambio->properties['attributes']['activo']);
    }

    public function test_guardar_sin_cambios_no_registra_nada(): void
    {
        $luis = $this->usuarioConRol(Rol::GestorTienda);
        Activity::query()->delete();

        Livewire::test(EditUser::class, ['record' => $luis->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(0, Activity::count());
    }

    public function test_los_accesos_correctos_y_fallidos_quedan_registrados(): void
    {
        auth()->logout();
        $luis = $this->usuarioConRol(Rol::GestorTienda, ['password' => 'clave-de-luis-123']);
        Activity::query()->delete();

        Livewire::test(Login::class)
            ->set('data.email', $luis->email)
            ->set('data.password', 'mal')
            ->call('authenticate');

        Livewire::test(Login::class)
            ->set('data.email', $luis->email)
            ->set('data.password', 'clave-de-luis-123')
            ->call('authenticate');

        $fallido = Activity::where('description', 'Intento de acceso fallido')->sole();
        $this->assertSame($luis->email, $fallido->properties['email']);
        $this->assertNull($fallido->causer);

        $correcto = Activity::where('description', 'Inicio de sesión')->sole();
        $this->assertTrue($correcto->causer->is($luis));
    }

    public function test_el_backend_muestra_la_actividad_en_espanol(): void
    {
        $luis = $this->usuarioConRol(Rol::GestorTienda, ['name' => 'Luis']);
        Activity::query()->delete();
        Livewire::test(EditUser::class, ['record' => $luis->getRouteKey()])
            ->fillForm(['name' => 'Luis García'])
            ->call('save');

        Livewire::test(ListActividades::class)
            ->assertOk()
            ->assertSee('Usuario modificado')
            ->assertSee('Nombre: Luis → Luis García')
            ->assertSee('Usuario: Luis García')
            ->assertSee('Ana');
    }

    public function test_la_actividad_no_se_puede_modificar_ni_borrar(): void
    {
        Livewire::test(EditUser::class, ['record' => $this->usuarioConRol(Rol::GestorTienda)->getRouteKey()])
            ->fillForm(['name' => 'Otro'])
            ->call('save');
        $actividad = Activity::firstOrFail();

        $this->assertFalse($this->superadministrador->can('update', $actividad));
        $this->assertFalse($this->superadministrador->can('delete', $actividad));
    }

    public function test_el_gestor_y_produccion_no_ven_la_actividad(): void
    {
        foreach ([Rol::GestorTienda, Rol::Produccion] as $rol) {
            $this->actingAs($this->usuarioConRol($rol))->get('/admin/registro-actividad')->assertForbidden();
        }
    }
}
