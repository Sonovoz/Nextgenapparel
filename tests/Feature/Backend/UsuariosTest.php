<?php

namespace Tests\Feature\Backend;

use App\Dominio\Sistema\Acceso\ReglasUsuarios;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class UsuariosTest extends TestCase
{
    use RefreshDatabase;

    private User $superadministrador;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->superadministrador = $this->usuarioConRol(atributos: ['name' => 'Ana Superadmin']);
        $this->actingAs($this->superadministrador);
    }

    public function test_lista_los_usuarios_con_su_rol(): void
    {
        $gestor = $this->usuarioConRol(Rol::GestorTienda, ['name' => 'Luis Gestor']);

        Livewire::test(ListUsers::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$this->superadministrador, $gestor])
            ->assertSee('Gestor de la tienda');
    }

    public function test_crea_un_usuario_con_rol_y_contrasena_cifrada(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Marta Producción',
                'email' => 'marta@ejemplo.es',
                'rol' => Rol::Produccion->value,
                'activo' => true,
                'password' => 'clave-de-marta-123',
                'password_confirmation' => 'clave-de-marta-123',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $marta = User::where('email', 'marta@ejemplo.es')->firstOrFail();

        $this->assertSame(Rol::Produccion, $marta->rol());
        $this->assertTrue(Hash::check('clave-de-marta-123', $marta->password));
    }

    public function test_al_crear_exige_contrasena_larga_y_confirmada(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Marta',
                'email' => 'marta@ejemplo.es',
                'rol' => Rol::Produccion->value,
                'password' => 'corta',
                'password_confirmation' => 'otra',
            ])
            ->call('create')
            ->assertHasFormErrors(['password']);

        $this->assertDatabaseMissing('users', ['email' => 'marta@ejemplo.es']);
    }

    public function test_no_admite_un_correo_repetido(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Otra Ana',
                'email' => $this->superadministrador->email,
                'rol' => Rol::Administrador->value,
                'password' => 'clave-larga-de-ana',
                'password_confirmation' => 'clave-larga-de-ana',
            ])
            ->call('create')
            ->assertHasFormErrors(['email' => 'unique']);
    }

    public function test_cambia_el_rol_y_desactiva_a_otro_usuario(): void
    {
        $luis = $this->usuarioConRol(Rol::GestorTienda);

        Livewire::test(EditUser::class, ['record' => $luis->getRouteKey()])
            ->assertFormSet(['rol' => Rol::GestorTienda->value, 'activo' => true])
            ->fillForm(['rol' => Rol::Administrador->value, 'activo' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $luis->refresh();
        $this->assertSame(Rol::Administrador, $luis->rol());
        $this->assertFalse($luis->activo);
    }

    public function test_editar_sin_contrasena_no_la_cambia(): void
    {
        $luis = $this->usuarioConRol(Rol::GestorTienda, ['password' => 'clave-original-123']);

        Livewire::test(EditUser::class, ['record' => $luis->getRouteKey()])
            ->fillForm(['name' => 'Luis Cambiado', 'password' => '', 'password_confirmation' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('clave-original-123', $luis->fresh()->password));
        $this->assertSame('Luis Cambiado', $luis->fresh()->name);
    }

    public function test_nadie_puede_cambiar_su_propio_rol_ni_desactivarse(): void
    {
        Livewire::test(EditUser::class, ['record' => $this->superadministrador->getRouteKey()])
            ->assertFormFieldIsDisabled('rol')
            ->assertFormFieldIsDisabled('activo')
            ->fillForm(['name' => 'Ana Renombrada'])
            ->call('save')
            ->assertHasNoFormErrors();

        $yo = $this->superadministrador->fresh();
        $this->assertSame('Ana Renombrada', $yo->name);
        $this->assertTrue($yo->esSuperadministrador());
        $this->assertTrue($yo->activo);
    }

    public function test_con_dos_superadministradores_uno_puede_quitar_el_rol_al_otro(): void
    {
        $segundo = $this->usuarioConRol();
        $this->actingAs($segundo);

        Livewire::test(EditUser::class, ['record' => $this->superadministrador->getRouteKey()])
            ->fillForm(['rol' => Rol::Administrador->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(Rol::Administrador, $this->superadministrador->fresh()->rol());
        $this->assertTrue($segundo->fresh()->esSuperadministrador());
    }

    public function test_no_se_puede_dejar_el_backend_sin_superadministradores(): void
    {
        // Hoy solo un superadministrador puede editar a otro, así que el
        // último solo podría «quitarse» a sí mismo (ya bloqueado). La regla
        // protege también ante cualquier otra vía futura.
        $otro = $this->usuarioConRol(Rol::Administrador);

        try {
            app(ReglasUsuarios::class)->validar($otro, $this->superadministrador, Rol::Administrador, true);
            $this->fail('Debería impedir quitar el rol al último superadministrador.');
        } catch (ValidationException $e) {
            $this->assertSame('Debe quedar al menos un superadministrador activo.', $e->errors()['data.rol'][0]);
        }

        $this->expectException(ValidationException::class);
        app(ReglasUsuarios::class)->validar($otro, $this->superadministrador, Rol::Superadministrador, false);
    }

    public function test_los_usuarios_no_se_borran(): void
    {
        $luis = $this->usuarioConRol(Rol::GestorTienda);

        $this->assertFalse($this->superadministrador->can('delete', $luis));

        Livewire::test(EditUser::class, ['record' => $luis->getRouteKey()])
            ->assertActionDoesNotExist('delete');
    }

    public function test_un_administrador_no_gestiona_usuarios(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::Administrador));

        $this->get('/admin/usuarios')->assertForbidden();
        $this->get('/admin/usuarios/nuevo')->assertForbidden();
    }

    public function test_un_usuario_desactivado_no_puede_entrar(): void
    {
        $luis = $this->usuarioConRol(Rol::GestorTienda, ['activo' => false, 'password' => 'clave-de-luis-123']);

        auth()->logout();

        Livewire::test(Login::class)
            ->set('data.email', $luis->email)
            ->set('data.password', 'clave-de-luis-123')
            ->call('authenticate')
            ->assertHasErrors(['data.email']);

        $this->assertGuest();
    }

    public function test_los_titulos_estan_en_espanol(): void
    {
        $this->get('/admin/usuarios/nuevo')->assertOk()->assertSee('Nuevo usuario');
        $this->get('/admin/usuarios/'.$this->superadministrador->getRouteKey().'/editar')->assertOk()->assertSee('Editar usuario');
    }
}
