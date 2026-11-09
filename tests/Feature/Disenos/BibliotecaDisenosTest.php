<?php

namespace Tests\Feature\Disenos;

use App\Dominio\Disenos\GuardarDiseno;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Resources\Disenos\DisenoResource;
use App\Filament\Resources\Disenos\Pages\ListDisenos;
use App\Models\Diseno;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\PngDePrueba;
use Tests\TestCase;

/** Biblioteca de diseños (paso 4.5): la del visitante en la tienda y la del backend. */
class BibliotecaDisenosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('Falta la extensión Imagick (en Herd: PHP → Extensions).');
        }
        Storage::fake(GuardarDiseno::DISCO);
    }

    /* ---------- Tienda: los diseños del visitante ---------- */

    public function test_el_visitante_ve_solo_sus_disenos_del_mas_reciente_al_mas_antiguo(): void
    {
        $this->getJson('/es/disenos')->assertOk()->assertExactJson(['disenos' => []]);

        $primero = $this->subir(600)->json('uuid');
        $segundo = $this->subir(700)->json('uuid');
        $this->getJson('/es/disenos')->assertOk()->assertJsonPath('disenos.*.uuid', [$segundo, $primero])
            ->assertJsonMissingPath('disenos.0.propietario')->assertJsonMissingPath('disenos.0.ruta')
            ->assertJsonMissingPath('disenos.0.sha256')->assertJsonMissingPath('disenos.0.id');

        $this->flushSession();
        $this->getJson('/es/disenos')->assertOk()->assertExactJson(['disenos' => []]);
    }

    public function test_el_visitante_borra_un_diseno_suyo_con_sus_archivos_y_no_los_de_otros(): void
    {
        $uuid = $this->subir(600)->json('uuid');
        $sesion = session()->all();

        $this->flushSession();
        $this->deleteJson("/es/disenos/{$uuid}")->assertNotFound();
        $this->assertSame(1, Diseno::count());

        $this->withSession($sesion)->deleteJson("/es/disenos/{$uuid}")->assertOk();
        $this->assertSame(0, Diseno::count());
        $this->assertSame([], Storage::disk('disenos')->allFiles());
    }

    /* ---------- Backend: Clientes → Biblioteca de diseños ---------- */

    public static function rolesConAcceso(): array
    {
        return [
            'superadministrador' => [Rol::Superadministrador, true],
            'administrador' => [Rol::Administrador, true],
            'gestor de la tienda' => [Rol::GestorTienda, true],
            'producción' => [Rol::Produccion, false],
        ];
    }

    #[DataProvider('rolesConAcceso')]
    public function test_la_biblioteca_y_sus_archivos_solo_con_la_seccion_clientes(Rol $rol, bool $accede): void
    {
        $diseno = $this->disenoSubido();
        $this->actingAs($this->usuarioConRol($rol));

        $this->get(DisenoResource::getUrl('index', panel: 'admin'))->assertStatus($accede ? 200 : 403);
        $this->get(DisenoResource::getUrl('view', ['record' => $diseno], panel: 'admin'))->assertStatus($accede ? 200 : 403);
        $this->get(route('backend.disenos.miniatura', $diseno))->assertStatus($accede ? 200 : 404);
        $this->get(route('backend.disenos.original', $diseno))->assertStatus($accede ? 200 : 404);
    }

    public function test_un_usuario_desactivado_con_la_sesion_abierta_no_descarga_nada(): void
    {
        $diseno = $this->disenoSubido();
        $this->actingAs($this->usuarioConRol(Rol::Superadministrador, ['activo' => false]));

        // La cuenta desactivada sale en la primera petición (CuentaActiva) y ya no descarga nada.
        $this->get(route('backend.disenos.miniatura', $diseno))->assertRedirect();
        $this->assertGuest();
        $this->get(route('backend.disenos.original', $diseno))->assertNotFound();
        $this->assertDatabaseMissing('activity_log', ['description' => 'Original de un diseño descargado']);
    }

    public function test_el_original_se_descarga_siempre_como_png(): void
    {
        $uuid = $this->postJson('/es/disenos', [
            'archivo' => new UploadedFile(PngDePrueba::crear(600, 600, 'white'), 'factura.html', 'text/html', null, true),
            'derechos' => '1',
        ])->assertCreated()->json('uuid');
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));

        $descarga = $this->get(route('backend.disenos.original', Diseno::where('uuid', $uuid)->sole()))->assertOk();

        $this->assertStringContainsString('filename=factura.png', (string) $descarga->headers->get('Content-Disposition'));
    }

    public function test_sin_sesion_los_archivos_del_backend_no_existen(): void
    {
        $diseno = $this->disenoSubido();

        $this->get(route('backend.disenos.miniatura', $diseno))->assertNotFound();
        $this->get(route('backend.disenos.original', $diseno))->assertNotFound();
    }

    public function test_la_ficha_muestra_lo_que_el_taller_necesita_y_la_descarga_queda_registrada(): void
    {
        $diseno = $this->disenoSubido(3000);
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));

        // 3000 px a 300 ppp: 25,4 cm.
        $this->get(DisenoResource::getUrl('view', ['record' => $diseno], panel: 'admin'))->assertOk()
            ->assertSee('3000 × 3000 px')->assertSee('25,4 × 25,4 cm')
            ->assertSee(__('disenos.derechos_casilla'));

        $descarga = $this->get(route('backend.disenos.original', $diseno))->assertOk();
        $this->assertSame(Storage::disk('disenos')->get($diseno->ruta), $descarga->streamedContent());
        $this->assertStringContainsString('diseno.png', (string) $descarga->headers->get('Content-Disposition'));
        $this->assertDatabaseHas('activity_log', ['log_name' => 'disenos', 'description' => 'Original de un diseño descargado', 'subject_id' => $diseno->id]);
    }

    public function test_borrar_desde_el_backend_quita_los_archivos_y_queda_registrado(): void
    {
        $diseno = $this->disenoSubido();
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));

        Livewire::test(ListDisenos::class)->callTableAction(DeleteAction::class, $diseno);

        $this->assertModelMissing($diseno);
        $this->assertSame([], Storage::disk('disenos')->allFiles());
        $this->assertDatabaseHas('activity_log', ['log_name' => 'disenos', 'description' => 'Diseño borrado']);
    }

    private function subir(int $lado): TestResponse
    {
        return $this->postJson('/es/disenos', [
            'archivo' => new UploadedFile(PngDePrueba::crear($lado, $lado, 'white'), 'diseno.png', 'image/png', null, true),
            'derechos' => '1',
        ])->assertCreated();
    }

    private function disenoSubido(int $lado = 600): Diseno
    {
        $uuid = $this->subir($lado)->json('uuid');
        $this->flushSession();

        return Diseno::where('uuid', $uuid)->sole();
    }
}
