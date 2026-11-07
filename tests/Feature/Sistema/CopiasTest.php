<?php

namespace Tests\Feature\Sistema;

use App\Dominio\Sistema\Acceso\Rol;
use App\Dominio\Sistema\Copias\Copias;
use App\Dominio\Sistema\Errores\ErrorDeCopia;
use App\Dominio\Sistema\Salud\Comprobaciones\CopiasSeguridad;
use App\Dominio\Sistema\Salud\EstadoSalud;
use App\Filament\Pages\CopiasSeguridad as PaginaCopias;
use Exception;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Backup\Events\BackupHasFailed;
use Tests\TestCase;
use ZipArchive;

/**
 * Sin transacciones (DatabaseMigrations): VACUUM INTO no se puede ejecutar
 * dentro de una transacción.
 */
class CopiasTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('copias');
        config(['backup.backup.password' => null]);
        @unlink(Copias::archivoPrueba());
        Filament::setCurrentPanel('admin');
    }

    protected function tearDown(): void
    {
        @unlink(Copias::archivoPrueba());

        parent::tearDown();
    }

    /** Contraseña como si viniera del .env al arrancar (el paquete la lee una sola vez). */
    private function conContrasena(?string $clave): void
    {
        config(['backup.backup.password' => $clave]);
        $this->app->forgetScopedInstances();
    }

    /** @return list<string> */
    private function contenidoDeLaUltima(): array
    {
        $zip = new ZipArchive;
        $zip->open(Storage::disk('copias')->path(Copias::listar()[0]['ruta']));
        $nombres = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombres[] = $zip->getNameIndex($i);
        }

        $zip->close();

        return $nombres;
    }

    public function test_hace_una_copia_con_la_base_de_datos_y_los_archivos(): void
    {
        Storage::disk('local')->put('disenos/prueba.png', 'imagen');

        $this->assertNull(app(Copias::class)->hacer());

        $copias = Copias::listar();
        $this->assertCount(1, $copias);
        $this->assertSame('copias', $copias[0]['disco']);
        $this->assertGreaterThan(0, $copias[0]['bytes']);

        $contenido = $this->contenidoDeLaUltima();
        $this->assertContains('copias-temp/'.Copias::ARCHIVO_SQLITE, $contenido);
        $this->assertContains('app/private/disenos/prueba.png', $contenido);

        // La carpeta temporal queda vacía.
        $this->assertSame([], glob(storage_path('copias-temp/*.sqlite')));
        Storage::disk('local')->delete('disenos/prueba.png');
    }

    public function test_la_copia_se_puede_restaurar(): void
    {
        app(Copias::class)->hacer();

        $resultado = app(Copias::class)->probarRestauracion();

        $this->assertTrue($resultado['correcto'], $resultado['mensaje']);
        $this->assertStringContainsString('restaurable', $resultado['mensaje']);
        $this->assertTrue(Copias::ultimaPrueba()['correcto']);
    }

    public function test_con_contrasena_la_copia_va_cifrada_y_se_restaura_con_ella(): void
    {
        $this->conContrasena('clave-de-las-copias');
        app(Copias::class)->hacer();

        $zip = new ZipArchive;
        $zip->open(Storage::disk('copias')->path(Copias::listar()[0]['ruta']));
        $sqlite = $zip->statName('copias-temp/'.Copias::ARCHIVO_SQLITE);
        $this->assertNotSame(ZipArchive::EM_NONE, $sqlite['encryption_method']);
        $zip->close();

        $this->assertTrue(app(Copias::class)->probarRestauracion()['correcto']);
    }

    public function test_con_la_contrasena_equivocada_la_prueba_falla_y_avisa(): void
    {
        Exceptions::fake();
        $this->conContrasena('clave-de-las-copias');
        app(Copias::class)->hacer();
        $this->conContrasena('otra-clave');

        $resultado = app(Copias::class)->probarRestauracion();

        $this->assertFalse($resultado['correcto']);
        Exceptions::assertReported(ErrorDeCopia::class);
    }

    public function test_sin_copias_la_prueba_falla(): void
    {
        Exceptions::fake();

        $this->assertFalse(app(Copias::class)->probarRestauracion()['correcto']);
    }

    public function test_una_copia_fallida_del_paquete_va_al_log_de_errores(): void
    {
        Exceptions::fake();

        event(new BackupHasFailed(new Exception('Disco lleno')));

        Exceptions::assertReported(fn (ErrorDeCopia $e) => str_contains($e->getMessage(), 'Disco lleno'));
    }

    public function test_la_comprobacion_de_salud_de_las_copias(): void
    {
        $comprobacion = new CopiasSeguridad;

        $this->assertSame(EstadoSalud::Aviso, $comprobacion->comprobar()[0]);

        $this->app['env'] = 'production';
        $this->assertSame(EstadoSalud::Fallo, $comprobacion->comprobar()[0]);
        $this->app['env'] = 'testing';

        app(Copias::class)->hacer();
        $this->assertSame(EstadoSalud::Correcto, $comprobacion->comprobar()[0]);

        $this->travel(27)->hours();
        $this->assertSame(EstadoSalud::Aviso, $comprobacion->comprobar()[0]);
    }

    public function test_si_la_ultima_prueba_fallo_la_salud_falla(): void
    {
        Exceptions::fake();
        $this->conContrasena('clave-de-las-copias');
        app(Copias::class)->hacer();
        $this->conContrasena('otra-clave');
        app(Copias::class)->probarRestauracion();

        $this->assertSame(EstadoSalud::Fallo, (new CopiasSeguridad)->comprobar()[0]);
    }

    public function test_los_comandos_de_copia_y_prueba(): void
    {
        $this->artisan('nga:copia-seguridad')->expectsOutputToContain('Copia hecha')->assertSuccessful();
        $this->artisan('nga:copia-seguridad --probar')->expectsOutputToContain('restaurable')->assertSuccessful();
    }

    public function test_la_retencion_es_7_diarias_4_semanales_12_mensuales(): void
    {
        $retencion = config('backup.cleanup.default_strategy');

        $this->assertSame(7, $retencion['keep_daily_backups_for_days']);
        $this->assertSame(4, $retencion['keep_weekly_backups_for_weeks']);
        $this->assertSame(12, $retencion['keep_monthly_backups_for_months']);
    }

    public function test_las_copias_estan_programadas(): void
    {
        Artisan::call('schedule:list');
        $lista = Artisan::output();

        $this->assertStringContainsString('backup:clean', $lista);
        $this->assertStringContainsString('nga:copia-seguridad', $lista);
        $this->assertStringContainsString('nga:copia-seguridad --probar', $lista);
    }

    public function test_el_backend_hace_la_copia_y_la_lista(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::Administrador));

        Livewire::test(PaginaCopias::class)
            ->callAction('hacerCopia')
            ->assertNotified('Copia hecha');

        $this->assertCount(1, Copias::listar());
        $this->get('/admin/copias-seguridad')->assertOk()->assertSee('Este servidor')->assertDontSee('Descargar');
    }

    public function test_solo_el_superadministrador_descarga_copias(): void
    {
        app(Copias::class)->hacer();
        $copia = Copias::listar()[0];

        $this->actingAs($this->usuarioConRol(Rol::Administrador));
        Livewire::test(PaginaCopias::class)->call('descargar', $copia['disco'], $copia['ruta'])->assertForbidden();

        $this->actingAs($this->usuarioConRol());
        Livewire::test(PaginaCopias::class)->call('descargar', $copia['disco'], $copia['ruta'])->assertFileDownloaded($copia['nombre']);
    }

    public function test_no_se_puede_descargar_un_archivo_que_no_es_una_copia(): void
    {
        $this->actingAs($this->usuarioConRol());

        Livewire::test(PaginaCopias::class)->call('descargar', 'local', '../.env')->assertNotFound();
    }

    public function test_el_gestor_y_produccion_no_ven_las_copias(): void
    {
        foreach ([Rol::GestorTienda, Rol::Produccion] as $rol) {
            $this->actingAs($this->usuarioConRol($rol))->get('/admin/copias-seguridad')->assertForbidden();
        }
    }
}
