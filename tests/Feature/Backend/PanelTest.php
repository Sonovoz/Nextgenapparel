<?php

namespace Tests\Feature\Backend;

use App\Filament\AvatarIniciales;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Filament\Pages\Pendientes\PaginaPendiente;
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
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('paginasPendientes')]
    public function test_cada_seccion_pendiente_explica_cuando_llega(string $clase): void
    {
        $this->actingAs($this->usuarioConRol())
            ->get($clase::getUrl(panel: 'admin'))
            ->assertOk()
            ->assertSee($clase::getNavigationLabel())
            ->assertSee('Disponible en la '.$clase::getDisponibleEn());
    }

    public function test_todas_las_secciones_tienen_grupo_icono_y_descripcion(): void
    {
        $paginas = self::clasesPendientes();

        $this->assertCount(8, $paginas); // Impuestos, Envío, Cupones y Reglas de precio (fase 3) y Clientes (fase 5) ya existen

        foreach ($paginas as $clase) {
            $this->assertInstanceOf(GrupoNavegacion::class, $clase::getNavigationGroup(), $clase);
            $this->assertNotNull($clase::getNavigationIcon(), $clase);
            $this->assertNotSame('', $clase::getDescripcion(), $clase);
        }
    }

    public function test_el_backend_no_carga_nada_de_servidores_externos(): void
    {
        $html = $this->actingAs($this->usuarioConRol(atributos: ['name' => 'Ana Pérez']))
            ->get('/admin')
            ->assertOk()
            ->getContent();

        preg_match_all('/(?:src|href)="(https?:\/\/[^"]+)"/', $html, $coincidencias);
        $externas = array_filter($coincidencias[1], fn (string $url) => ! str_starts_with($url, url('/')));

        $this->assertSame([], array_values($externas));
        $this->assertStringNotContainsString('ui-avatars.com', $html);
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

    public static function paginasPendientes(): array
    {
        $datos = [];

        foreach (self::clasesPendientes() as $clase) {
            $datos[class_basename($clase)] = [$clase];
        }

        return $datos;
    }

    /** @return list<class-string<PaginaPendiente>> */
    private static function clasesPendientes(): array
    {
        $clases = [];

        foreach (glob(dirname(__DIR__, 3).'/app/Filament/Pages/Pendientes/*.php') ?: [] as $archivo) {
            $clase = 'App\\Filament\\Pages\\Pendientes\\'.basename($archivo, '.php');

            if ($clase !== PaginaPendiente::class) {
                $clases[] = $clase;
            }
        }

        return $clases;
    }

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }
}
