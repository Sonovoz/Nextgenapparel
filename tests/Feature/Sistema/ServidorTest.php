<?php

namespace Tests\Feature\Sistema;

use App\Dominio\Sistema\Acceso\Rol;
use App\Dominio\Sistema\Servidor\InformeServidor;
use App\Filament\Pages\Servidor;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Tests\TestCase;

/** Herramientas → Servidor (D207): lo que necesita el portal en el servidor, sin contraseñas. */
class ServidorTest extends TestCase
{
    use RefreshDatabase;

    public function test_muestra_php_extensiones_y_base_de_datos_sin_contrasenas(): void
    {
        // La contraseña de la conexión que se usa (SQLite en local, MariaDB en la CI) y la de Redis nunca salen.
        $conexion = config('database.default');
        config()->set("database.connections.{$conexion}.password", 'clave-muy-secreta');
        config()->set('database.redis.default.password', 'otra-clave-secreta');
        $version = collect(InformeServidor::filas())->firstWhere('nombre', 'Tipo y versión')['valor'];

        $this->actingAs($this->usuarioConRol(Rol::Administrador))->get(Servidor::getUrl(panel: 'admin'))->assertOk()
            ->assertSee(PHP_VERSION)->assertSee('pdo_mysql')->assertSee('imagick')->assertSee($version)
            ->assertDontSee('clave-muy-secreta')->assertDontSee('otra-clave-secreta');
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda))->get(Servidor::getUrl(panel: 'admin'))->assertForbidden();
    }

    public function test_avisa_si_falta_lo_imprescindible(): void
    {
        $filas = collect(InformeServidor::filas());

        $this->assertSame(version_compare(PHP_VERSION, '8.3.0', '>=') ? 'correcto' : 'fallo', $filas->firstWhere('nombre', 'Versión')['estado']);
        $this->assertSame(extension_loaded('imagick') ? 'correcto' : 'fallo', $filas->firstWhere('nombre', 'imagick')['estado']);
        config()->set('app.debug', true);
        $this->app->detectEnvironment(fn () => 'production');
        $this->assertSame('fallo', collect(InformeServidor::filas())->firstWhere('nombre', 'Depuración (APP_DEBUG)')['estado']);
    }

    public function test_avisa_si_app_url_no_es_la_direccion_por_la_que_se_entra(): void
    {
        // Las fotos se forman con APP_URL: con la de local («.test») en el servidor no se ven.
        config()->set('app.url', 'http://nextgenapparel.test');
        $this->app->instance('request', Request::create('https://nextgen.ejemplo.com/admin/servidor'));

        $this->assertFalse(InformeServidor::urlCorrecta());
        config()->set('app.url', 'https://nextgen.ejemplo.com');
        $this->assertTrue(InformeServidor::urlCorrecta());
    }

    public function test_crea_el_enlace_de_las_fotos_desde_el_backend(): void
    {
        $enlace = public_path('storage');
        $habia = is_link($enlace) ? readlink($enlace) : null;
        if ($habia !== null) {
            unlink($enlace);
        }
        // Un enlace roto, como el que queda al copiar la carpeta desde otro ordenador.
        symlink('/no/existe', $enlace);

        try {
            $this->assertFalse(InformeServidor::enlaceCorrecto());
            Filament::setCurrentPanel('admin');
            $this->actingAs($this->usuarioConRol(Rol::Administrador));
            Livewire::test(Servidor::class)->callAction('enlace')->assertNotified('Enlace de las fotos creado');
            $this->assertTrue(InformeServidor::enlaceCorrecto());
        } finally {
            if (is_link($enlace)) {
                unlink($enlace);
            }
            if ($habia !== null) {
                symlink($habia, $enlace);
            }
        }
    }
}
