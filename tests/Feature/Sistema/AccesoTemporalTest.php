<?php

namespace Tests\Feature\Sistema;

use App\Dominio\Sistema\Acceso\Rol;
use App\Dominio\Sistema\Instalacion\AccesoTemporal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** D208: primera instalación sin consola; clave temporal en un archivo del servidor que se borra al crear el superadministrador. */
class AccesoTemporalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function clave(): string
    {
        preg_match('/^CLAVE: (\S+)$/m', (string) Storage::disk('local')->get(AccesoTemporal::ARCHIVO), $c);

        return $c[1];
    }

    /** @return array<string, string> */
    private function datos(string $clave): array
    {
        return ['clave' => $clave, 'nombre' => 'Ana', 'email' => 'ana@ejemplo.es', 'password' => 'una-clave-larga-123', 'password_confirmation' => 'una-clave-larga-123'];
    }

    public function test_sin_superadministrador_el_login_lleva_a_instalar_y_se_crea_la_clave(): void
    {
        $this->get('/admin/login')->assertRedirect(route('instalacion'));

        $this->get('/instalar')->assertOk()->assertSee('acceso-temporal.txt');
        Storage::disk('local')->assertExists(AccesoTemporal::ARCHIVO);
        $this->assertSame(32, strlen($this->clave()));
    }

    public function test_con_la_clave_crea_el_superadministrador_cifrado_entra_y_borra_la_clave(): void
    {
        $this->get('/instalar');

        $this->post('/instalar', $this->datos($this->clave()))->assertRedirect('/admin');

        $usuario = User::where('email', 'ana@ejemplo.es')->firstOrFail();
        $this->assertSame(Rol::Superadministrador, $usuario->rol());
        $this->assertTrue(Hash::check('una-clave-larga-123', $usuario->password));
        $this->assertAuthenticatedAs($usuario);
        Storage::disk('local')->assertMissing(AccesoTemporal::ARCHIVO);

        $this->get('/instalar')->assertNotFound();
        $this->post('/instalar', $this->datos('lo-que-sea'))->assertNotFound();
    }

    public function test_con_una_clave_equivocada_no_crea_nada(): void
    {
        $this->get('/instalar');

        $this->post('/instalar', $this->datos('clave-equivocada'))->assertSessionHasErrors('clave');

        $this->assertDatabaseCount('users', 0);
        Storage::disk('local')->assertExists(AccesoTemporal::ARCHIVO);
    }

    public function test_si_ya_hay_superadministrador_no_existe_y_borra_un_archivo_que_quedara(): void
    {
        User::factory()->create()->assignRole(Rol::Superadministrador->value);
        Storage::disk('local')->put(AccesoTemporal::ARCHIVO, 'CLAVE: '.str_repeat('a', 32));

        $this->get('/instalar')->assertNotFound();
        Storage::disk('local')->assertMissing(AccesoTemporal::ARCHIVO);
        $this->get('/admin/login')->assertOk();
    }

    public function test_un_cliente_registrado_no_cierra_la_instalacion(): void
    {
        User::factory()->create();

        $this->assertTrue(AccesoTemporal::pendiente());
    }
}
