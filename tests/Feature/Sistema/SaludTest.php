<?php

namespace Tests\Feature\Sistema;

use App\Dominio\Sistema\Acceso\Rol;
use App\Dominio\Sistema\Errores\ErrorDePrueba;
use App\Dominio\Sistema\Salud\Comprobaciones\Colas;
use App\Dominio\Sistema\Salud\Comprobaciones\Comprobacion;
use App\Dominio\Sistema\Salud\Comprobaciones\Configuracion;
use App\Dominio\Sistema\Salud\Comprobaciones\Correo;
use App\Dominio\Sistema\Salud\Comprobaciones\EspacioDisco;
use App\Dominio\Sistema\Salud\Comprobaciones\TareasProgramadas;
use App\Dominio\Sistema\Salud\ComprobarSalud;
use App\Dominio\Sistema\Salud\EstadoSalud;
use App\Dominio\Sistema\Salud\Latido;
use App\Dominio\Sistema\Salud\Resultado;
use App\Filament\Pages\EstadoSistema;
use App\Jobs\LatidoCola;
use App\Notifications\AlertaSalud;
use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class SaludTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->limpiarArchivos();
        config(['errores.correo_alertas' => 'alertas@ejemplo.test', 'errores.alerta_cada_minutos' => 60]);
        config(['queue.default' => 'database', 'mail.default' => 'log']);
        Notification::fake();
        Filament::setCurrentPanel('admin');

        // El disco real de la máquina de pruebas no debe influir: se simula
        // uno con espacio de sobra (el cálculo real tiene su propio test).
        $this->app->bind(EspacioDisco::class, fn () => new class extends EspacioDisco
        {
            public function comprobar(): array
            {
                return self::evaluar(50 * 1024 ** 3, 100 * 1024 ** 3);
            }
        });
    }

    protected function tearDown(): void
    {
        $this->limpiarArchivos();

        parent::tearDown();
    }

    private function limpiarArchivos(): void
    {
        @unlink(ComprobarSalud::archivo());
        File::deleteDirectory(storage_path('framework/latidos'));
    }

    private function todoEnMarcha(): void
    {
        Latido::registrar(Latido::TAREAS);
        Latido::registrar(Latido::COLAS);
    }

    private function resultado(string $clave): Resultado
    {
        return collect(ComprobarSalud::ultimosResultados())->firstWhere('clave', $clave);
    }

    private function avisos(?bool $recuperado = null): int
    {
        return Notification::sent(new AnonymousNotifiable, AlertaSalud::class)
            ->filter(fn (AlertaSalud $a) => $recuperado === null || $a->recuperado === $recuperado)
            ->count();
    }

    /** Sustituye una comprobación por otra que siempre falla. */
    private function hacerFallar(string $clase): void
    {
        $this->app->bind($clase, fn () => new class($clase) implements Comprobacion
        {
            public function __construct(private string $clase) {}

            public function clave(): string
            {
                return (new $this->clase)->clave();
            }

            public function nombre(): string
            {
                return 'Prueba';
            }

            public function comprobar(): array
            {
                return Resultado::fallo('Falla a propósito.');
            }
        });
    }

    public function test_con_todo_en_marcha_todas_las_comprobaciones_son_correctas_o_avisos(): void
    {
        $this->todoEnMarcha();

        $resultados = app(ComprobarSalud::class)();

        $this->assertCount(count(ComprobarSalud::comprobaciones()), $resultados);
        $this->assertSame(EstadoSalud::Correcto, $this->resultado('base-de-datos')->estado);
        $this->assertSame(EstadoSalud::Correcto, $this->resultado('cache')->estado);
        $this->assertSame(EstadoSalud::Correcto, $this->resultado('tareas-programadas')->estado);
        $this->assertSame(EstadoSalud::Correcto, $this->resultado('colas')->estado);
        $this->assertSame(EstadoSalud::Correcto, $this->resultado('correo')->estado);
    }

    public function test_las_tareas_programadas_fallan_si_no_hay_latido_reciente(): void
    {
        $this->app['env'] = 'local';
        [$estado, $mensaje] = (new TareasProgramadas)->comprobar();
        $this->assertSame(EstadoSalud::Fallo, $estado);
        $this->assertStringContainsString('schedule:work', $mensaje);
        $this->app['env'] = 'testing';

        Latido::registrar(Latido::TAREAS);
        $this->assertSame(EstadoSalud::Correcto, (new TareasProgramadas)->comprobar()[0]);

        $this->travel(4)->minutes();
        $this->assertSame(EstadoSalud::Fallo, (new TareasProgramadas)->comprobar()[0]);
    }

    public function test_las_colas_fallan_sin_latido_y_avisan_de_trabajos_fallidos(): void
    {
        $this->assertSame(EstadoSalud::Fallo, (new Colas)->comprobar()[0]);

        Latido::registrar(Latido::COLAS);
        $this->assertSame(EstadoSalud::Correcto, (new Colas)->comprobar()[0]);

        DB::table('failed_jobs')->insert([
            'uuid' => 'a', 'connection' => 'database', 'queue' => 'default',
            'payload' => '{}', 'exception' => 'x', 'failed_at' => now(),
        ]);
        [$estado, $mensaje] = (new Colas)->comprobar();
        $this->assertSame(EstadoSalud::Aviso, $estado);
        $this->assertStringContainsString('1 trabajo fallido', $mensaje);
    }

    public function test_sin_cola_las_colas_son_correctas(): void
    {
        config(['queue.default' => 'sync']);

        $this->assertSame(EstadoSalud::Correcto, (new Colas)->comprobar()[0]);
    }

    public function test_el_trabajo_de_latido_registra_la_cola(): void
    {
        (new LatidoCola)->handle();

        $this->assertNotNull(Latido::ultimo(Latido::COLAS));
    }

    public function test_el_espacio_en_disco_avisa_y_falla_segun_lo_libre(): void
    {
        $gb = 1024 ** 3;

        $this->assertSame(EstadoSalud::Correcto, EspacioDisco::evaluar(50 * $gb, 100 * $gb)[0]);
        $this->assertSame(EstadoSalud::Aviso, EspacioDisco::evaluar(10 * $gb, 100 * $gb)[0]);
        $this->assertSame(EstadoSalud::Fallo, EspacioDisco::evaluar(4 * $gb, 100 * $gb)[0]);
        $this->assertStringContainsString('10,0 GB libres de 100,0 GB (10 %)', EspacioDisco::evaluar(10 * $gb, 100 * $gb)[1]);
    }

    public function test_el_espacio_en_disco_se_mide_de_verdad(): void
    {
        [$estado, $mensaje] = (new EspacioDisco(storage_path()))->comprobar();

        $this->assertInstanceOf(EstadoSalud::class, $estado);
        $this->assertStringContainsString('GB libres de', $mensaje);
    }

    public function test_el_correo_smtp_falla_si_el_servidor_no_responde(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);

        [$estado, $mensaje] = (new Correo)->comprobar();

        $this->assertSame(EstadoSalud::Fallo, $estado);
        $this->assertStringContainsString('127.0.0.1:1', $mensaje);
    }

    public function test_en_produccion_la_depuracion_activa_es_un_fallo(): void
    {
        $this->app['env'] = 'production';
        config(['app.debug' => true]);

        $this->assertSame(EstadoSalud::Fallo, (new Configuracion)->comprobar()[0]);

        config(['app.debug' => false]);
        $this->assertSame(EstadoSalud::Correcto, (new Configuracion)->comprobar()[0]);
    }

    public function test_los_errores_criticos_pendientes_dan_un_aviso(): void
    {
        report(new ErrorDePrueba('x'));
        $this->todoEnMarcha();

        app(ComprobarSalud::class)(avisar: false);

        $this->assertSame(EstadoSalud::Aviso, $this->resultado('errores-criticos')->estado);
    }

    public function test_si_una_comprobacion_lanza_un_error_las_demas_siguen(): void
    {
        $this->app->bind(TareasProgramadas::class, fn () => new class extends TareasProgramadas
        {
            public function comprobar(): array
            {
                throw new RuntimeException('Rota');
            }
        });

        $resultados = app(ComprobarSalud::class)(avisar: false);

        $this->assertCount(count(ComprobarSalud::comprobaciones()), $resultados);
        $this->assertSame(EstadoSalud::Fallo, $this->resultado('tareas-programadas')->estado);
        $this->assertStringContainsString('Rota', $this->resultado('tareas-programadas')->mensaje);
    }

    public function test_avisa_al_fallar_no_repite_durante_una_hora_y_avisa_al_recuperarse(): void
    {
        $this->todoEnMarcha();
        $this->hacerFallar(Correo::class);

        app(ComprobarSalud::class)();
        app(ComprobarSalud::class)();
        $this->assertSame(1, $this->avisos(recuperado: false));

        $this->travel(61)->minutes();
        $this->todoEnMarcha();
        app(ComprobarSalud::class)();
        $this->assertSame(2, $this->avisos(recuperado: false));

        $this->app->offsetUnset(Correo::class);
        app(ComprobarSalud::class)();
        $this->assertSame(1, $this->avisos(recuperado: true));

        app(ComprobarSalud::class)();
        $this->assertSame(1, $this->avisos(recuperado: true));
    }

    public function test_un_fallo_nuevo_avisa_aunque_ya_hubiera_otro(): void
    {
        $this->todoEnMarcha();
        $this->hacerFallar(Correo::class);
        app(ComprobarSalud::class)();

        $this->hacerFallar(EspacioDisco::class);
        app(ComprobarSalud::class)();

        $this->assertSame(2, $this->avisos(recuperado: false));
    }

    public function test_sin_correo_de_alertas_no_avisa(): void
    {
        config(['errores.correo_alertas' => null]);

        app(ComprobarSalud::class)();

        $this->assertSame(0, $this->avisos());
    }

    public function test_con_la_base_de_datos_caida_lo_detecta_y_avisa(): void
    {
        $this->todoEnMarcha();
        // Una conexión aparte que no responde, para no romper la de los tests.
        $original = config('database.default');
        config([
            'database.connections.caida' => ['driver' => 'sqlite', 'database' => '/no/existe/nextgenapparel.sqlite'],
            'database.default' => 'caida',
        ]);

        try {
            $resultados = app(ComprobarSalud::class)();
        } finally {
            config(['database.default' => $original]);
            DB::purge('caida');
        }

        $this->assertSame(EstadoSalud::Fallo, collect($resultados)->firstWhere('clave', 'base-de-datos')->estado);
        $this->assertSame(1, $this->avisos(recuperado: false));
        $this->assertFileExists(ComprobarSalud::archivo());
    }

    public function test_el_correo_de_aviso_lista_los_fallos(): void
    {
        $this->todoEnMarcha();
        $this->hacerFallar(Correo::class);
        app(ComprobarSalud::class)();

        $aviso = Notification::sent(new AnonymousNotifiable, AlertaSalud::class)->first();
        $correo = $aviso->toMail(new AnonymousNotifiable);

        $this->assertStringContainsString('1 fallo en el sistema', $correo->subject);
        $this->assertStringContainsString('Falla a propósito.', implode(' ', $correo->introLines));
        $this->assertStringContainsString('/admin/estado-sistema', $correo->actionUrl);
    }

    public function test_el_comando_muestra_el_estado_y_falla_si_algo_falla(): void
    {
        $this->artisan('nga:comprobar-salud --sin-avisos')
            ->expectsOutputToContain('Base de datos')
            ->assertFailed();

        $this->assertSame(0, $this->avisos());
    }

    public function test_las_tareas_automaticas_estan_programadas(): void
    {
        Artisan::call('schedule:list');
        $lista = Artisan::output();

        foreach (['Latido de las tareas programadas', 'Latido de la cola', 'nga:comprobar-salud', 'model:prune', 'activitylog:clean', 'queue:prune-failed'] as $tarea) {
            $this->assertStringContainsString($tarea, $lista);
        }
    }

    public function test_frecuencias_en_palabras(): void
    {
        $this->assertSame('Cada minuto', EstadoSistema::frecuencia('* * * * *'));
        $this->assertSame('Cada 5 minutos', EstadoSistema::frecuencia('*/5 * * * *'));
        $this->assertSame('Cada día a las 03:10', EstadoSistema::frecuencia('10 3 * * *'));
    }

    public function test_la_pagina_muestra_el_estado_y_las_tareas(): void
    {
        $this->todoEnMarcha();
        app(ComprobarSalud::class)(avisar: false);

        $this->actingAs($this->usuarioConRol(Rol::Administrador))
            ->get('/admin/estado-sistema')
            ->assertOk()
            ->assertSee('Base de datos')
            ->assertSee('Correcto')
            ->assertSee('Última comprobación')
            ->assertSee('Comprobar la salud del sistema')
            ->assertSee('Cada 5 minutos');
    }

    public function test_la_pagina_lista_las_tareas_aunque_no_se_hayan_cargado(): void
    {
        // Como en una petición web: sin las tareas de routes/console.php.
        $this->app->instance(Schedule::class, new Schedule);
        \Illuminate\Support\Facades\Schedule::clearResolvedInstances();

        $this->actingAs($this->usuarioConRol())
            ->get('/admin/estado-sistema')
            ->assertOk()
            ->assertSee('Comprobar la salud del sistema')
            ->assertSee('Borrar actividad de hace más de un año');
    }

    public function test_comprobar_ahora_actualiza_sin_enviar_correos(): void
    {
        $this->actingAs($this->usuarioConRol());

        Livewire::test(EstadoSistema::class)
            ->callAction('comprobarAhora')
            ->assertNotified();

        $this->assertNotNull(ComprobarSalud::ultimaComprobacion());
        $this->assertSame(0, $this->avisos());
    }

    public function test_el_gestor_y_produccion_no_ven_el_estado(): void
    {
        foreach ([Rol::GestorTienda, Rol::Produccion] as $rol) {
            $this->actingAs($this->usuarioConRol($rol))->get('/admin/estado-sistema')->assertForbidden();
        }
    }
}
