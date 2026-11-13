<?php

namespace Tests\Feature\Backend;

use App\Filament\AvatarIniciales;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Http\Middleware\IdiomaBackend;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Livewire\Livewire;
use Tests\TestCase;

class PanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_sesion_redirige_al_acceso(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_la_pantalla_de_acceso_esta_en_espanol(): void
    {
        $this->usuarioConRol(); // ya instalado (D208)
        $this->get('/admin/login', ['Accept-Language' => 'en-GB,en;q=0.9'])
            ->assertOk()
            ->assertSee('lang="es"', false)
            ->assertSee('Nextgenapparel')
            ->assertSee('Correo electrónico');
    }

    public function test_un_usuario_entra_con_su_correo_y_contrasena(): void
    {
        $usuario = $this->usuarioConRol(atributos: ['password' => 'clave-segura-123']);

        Livewire::test(Login::class)
            ->set('data.email', $usuario->email)
            ->set('data.password', 'clave-segura-123')
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_una_contrasena_incorrecta_no_entra(): void
    {
        $usuario = $this->usuarioConRol(atributos: ['password' => 'clave-segura-123']);

        Livewire::test(Login::class)
            ->set('data.email', $usuario->email)
            ->set('data.password', 'otra-clave')
            ->call('authenticate')
            ->assertHasErrors(['data.email']);

        $this->assertGuest();
    }

    public function test_el_escritorio_se_muestra_al_entrar(): void
    {
        $this->actingAs($this->usuarioConRol())
            ->get('/admin')
            ->assertOk()
            ->assertSee('Escritorio');
    }

    public function test_el_menu_sigue_el_orden_de_woocommerce(): void
    {
        $etiquetas = array_map(fn (GrupoNavegacion $g) => $g->getLabel(), GrupoNavegacion::cases());

        $this->actingAs($this->usuarioConRol())
            ->get('/admin')
            ->assertSeeInOrder(['Escritorio', ...$etiquetas]);
    }

    /** Versión 1 completa (D187): ya no queda ninguna sección «Disponible en la fase…» en el menú. */
    public function test_no_quedan_secciones_pendientes(): void
    {
        $this->assertDirectoryDoesNotExist(dirname(__DIR__, 3).'/app/Filament/Pages/Pendientes');
    }

    public function test_el_backend_no_carga_nada_de_servidores_externos(): void
    {
        $html = $this->actingAs($this->usuarioConRol(atributos: ['name' => 'Ana Pérez']))
            ->get('/admin')
            ->assertOk()
            ->getContent();

        // Lo que el navegador carga solo: src de imágenes y scripts y <link href> (estilos, fuentes, iconos).
        // Un enlace <a href> (p. ej. el crédito de Minimedia) no carga nada hasta que se pulsa.
        preg_match_all('/(?:\bsrc="|<link\b[^>]*\bhref=")(https?:\/\/[^"]+)"/', $html, $coincidencias);
        $externas = array_filter($coincidencias[1], fn (string $url) => ! str_starts_with($url, url('/')));

        $this->assertSame([], array_values($externas));
        $this->assertStringNotContainsString('ui-avatars.com', $html);
        $this->assertStringContainsString('<a class="credito-minimedia" href="https://www.minimedia.es"', $html); // un enlace, no una carga
    }

    public function test_el_avatar_muestra_las_iniciales(): void
    {
        $avatar = (new AvatarIniciales)->get(User::factory()->make(['name' => 'ana pérez lópez']));

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $avatar);
        $this->assertStringContainsString('>AP</text>', base64_decode(substr($avatar, 26)));
    }

    /**
     * Regresión del error 419 al entrar en Herd (http://nextgenapparel.test).
     *
     * Livewire repite el middleware persistente en sus peticiones internas,
     * que ya pasan por el grupo «web». Si las cookies o la sesión fueran
     * persistentes se procesarían dos veces y la sesión se perdería. En los
     * tests no se ve como 419 porque Laravel no comprueba el token CSRF.
     */
    public function test_solo_el_idioma_es_middleware_persistente_de_livewire(): void
    {
        $persistentes = Livewire::getPersistentMiddleware();

        $this->assertContains(IdiomaBackend::class, $persistentes);

        foreach ([
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            PreventRequestForgery::class,
        ] as $middleware) {
            $this->assertNotContains($middleware, $persistentes, $middleware.' no debe ser persistente');
        }
    }

    public function test_el_frontend_no_se_ve_afectado(): void
    {
        $this->get('/es')->assertOk()->assertSee('Creamos marcas que comunican');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }
}
