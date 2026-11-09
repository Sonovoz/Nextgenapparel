<?php

namespace Tests\Feature\Backend;

use App\Dominio\Sistema\Acceso\Rol;
use App\Dominio\Sistema\Acceso\SincronizarRoles;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Filament\Pages\AjustesGenerales;
use App\Filament\Pages\CopiasSeguridad;
use App\Filament\Pages\Informes;
use App\Filament\Pages\Produccion\OrdenesTrabajo;
use App\Filament\Pages\RolesPermisos;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_existen_los_cuatro_roles_con_sus_permisos(): void
    {
        $this->assertEqualsCanonicalizing(Rol::valores(), Role::pluck('name')->all());

        foreach (Rol::cases() as $rol) {
            $this->assertEqualsCanonicalizing(
                array_map(fn (GrupoNavegacion $g) => $g->permiso(), $rol->grupos()),
                Role::findByName($rol->value)->permissions->pluck('name')->all(),
                $rol->value,
            );
        }
    }

    public function test_sincronizar_dos_veces_no_duplica_nada(): void
    {
        (new SincronizarRoles)();
        (new SincronizarRoles)();

        $this->assertSame(4, Role::count());
        $this->assertDatabaseCount('permissions', count(GrupoNavegacion::cases()));
    }

    public function test_el_gestor_de_la_tienda_ve_analiticas_pero_no_ajustes_ni_herramientas(): void
    {
        $this->assertTrue(Rol::GestorTienda->puedeVer(GrupoNavegacion::Analiticas));
        $this->assertFalse(Rol::GestorTienda->puedeVer(GrupoNavegacion::Ajustes));
        $this->assertFalse(Rol::GestorTienda->puedeVer(GrupoNavegacion::Herramientas));
    }

    public function test_un_usuario_sin_rol_no_entra_en_el_backend(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_un_usuario_desactivado_no_entra_aunque_tenga_rol(): void
    {
        $this->actingAs($this->usuarioConRol(atributos: ['activo' => false]))->get('/admin')->assertForbidden();
    }

    #[DataProvider('roles')]
    public function test_cada_rol_ve_en_el_menu_solo_sus_grupos(Rol $rol): void
    {
        $respuesta = $this->actingAs($this->usuarioConRol($rol))->get('/admin')->assertOk();

        foreach (GrupoNavegacion::cases() as $grupo) {
            $etiqueta = '>'.e($grupo->getLabel()).'<';

            $rol->puedeVer($grupo)
                ? $respuesta->assertSee($grupo->getLabel())
                : $respuesta->assertDontSee($etiqueta, false);
        }
    }

    #[DataProvider('roles')]
    public function test_cada_rol_solo_puede_abrir_las_secciones_de_sus_grupos(Rol $rol): void
    {
        $this->actingAs($this->usuarioConRol($rol));

        // Una sección de cada grupo con páginas propias.
        foreach ([AjustesGenerales::class, OrdenesTrabajo::class, Informes::class, CopiasSeguridad::class] as $clase) {
            $respuesta = $this->get($clase::getUrl(panel: 'admin'));

            $rol->puedeVer($clase::getNavigationGroup())
                ? $respuesta->assertOk()
                : $respuesta->assertForbidden();
        }

        $usuarios = $rol->puedeVer(GrupoNavegacion::UsuariosYRoles);
        $this->get(UserResource::getUrl('index'))->assertStatus($usuarios ? 200 : 403);
        $this->get(RolesPermisos::getUrl())->assertStatus($usuarios ? 200 : 403);
    }

    public function test_la_pagina_de_roles_muestra_la_tabla_de_accesos(): void
    {
        $this->actingAs($this->usuarioConRol())
            ->get(RolesPermisos::getUrl())
            ->assertOk()
            ->assertSee('Qué ve cada rol en el menú')
            ->assertSeeInOrder(array_map(fn (Rol $r) => $r->getLabel(), Rol::cases()))
            ->assertSee('1 usuario activo');
    }

    public function test_la_migracion_hace_superadministradores_a_los_usuarios_anteriores(): void
    {
        $migracion = 'database/migrations/2026_11_06_161400_sincronizar_roles_y_permisos.php';
        $this->artisan('migrate:rollback', ['--path' => $migracion])->assertSuccessful();
        Role::query()->delete();

        $anterior = User::factory()->create();

        $this->artisan('migrate', ['--path' => $migracion])->assertSuccessful();

        $this->assertTrue($anterior->fresh()->esSuperadministrador());
    }

    public static function roles(): array
    {
        return array_combine(
            array_map(fn (Rol $r) => $r->value, Rol::cases()),
            array_map(fn (Rol $r) => [$r], Rol::cases()),
        );
    }
}
