<?php

namespace Tests\Feature\Disenos;

use App\Dominio\Disenos\GuardarDiseno;
use App\Dominio\Disenos\LimpiarDisenos;
use App\Models\Ajuste;
use App\Models\Diseno;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Retención de los diseños (paso 4.6): sin pedido y sin cuenta, se borran pasados los días de Ajustes. */
class LimpiarDisenosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(GuardarDiseno::DISCO);
    }

    public function test_borra_los_de_visitantes_pasados_los_dias_de_ajustes_y_conserva_el_resto(): void
    {
        Ajuste::guardar('disenos.dias_sin_pedido', 30);
        $caducado = $this->diseno(hace: 31);
        $reciente = $this->diseno(hace: 29);
        $deCliente = $this->diseno(hace: 400, cliente: 7);

        $this->artisan('disenos:limpiar')->expectsOutputToContain('Diseños borrados: 1.')->assertSuccessful();

        $this->assertModelMissing($caducado);
        $this->assertFalse(Storage::disk('disenos')->exists($caducado->ruta));
        $this->assertFalse(Storage::disk('disenos')->exists($caducado->miniatura));
        $this->assertModelExists($reciente);
        $this->assertModelExists($deCliente);
        $this->assertTrue(Storage::disk('disenos')->exists($deCliente->ruta));
        $this->assertDatabaseHas('activity_log', ['log_name' => 'disenos', 'description' => 'Limpieza de diseños']);
    }

    public function test_los_dias_salen_de_los_ajustes(): void
    {
        Ajuste::guardar('disenos.dias_sin_pedido', 7);
        $diseno = $this->diseno(hace: 8);

        (new LimpiarDisenos)();

        $this->assertModelMissing($diseno);
    }

    public function test_borra_los_archivos_huerfanos_de_mas_de_un_dia_y_no_los_de_una_subida_en_curso(): void
    {
        $diseno = $this->diseno(hace: 1);
        $disco = Storage::disk('disenos');
        $disco->put('2026/11/viejo-huerfano.png', 'x');
        touch($disco->path('2026/11/viejo-huerfano.png'), now()->subDays(2)->getTimestamp());
        $disco->put('2026/11/subiendo-ahora.png', 'x');

        $disco->put('2025/01/viejo.png', 'x');
        touch($disco->path('2025/01/viejo.png'), now()->subDays(2)->getTimestamp());

        $this->assertSame(['disenos' => 0, 'huerfanos' => 2], (new LimpiarDisenos)());
        $this->assertFalse($disco->directoryExists('2025/01'), 'La carpeta vacía se borra');
        $this->assertFalse($disco->directoryExists('2025'));
        $this->assertTrue($disco->directoryExists('2026/11'));

        $this->assertFalse($disco->exists('2026/11/viejo-huerfano.png'));
        $this->assertTrue($disco->exists('2026/11/subiendo-ahora.png'));
        $this->assertTrue($disco->exists($diseno->ruta));
        $this->assertTrue($disco->exists($diseno->miniatura));
    }

    public function test_sin_nada_que_borrar_no_ensucia_el_registro_de_actividad(): void
    {
        $this->diseno(hace: 1);

        (new LimpiarDisenos)();

        $this->assertDatabaseMissing('activity_log', ['description' => 'Limpieza de diseños']);
    }

    public function test_se_ejecuta_cada_noche(): void
    {
        $tarea = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command ?? '', 'disenos:limpiar'));

        $this->assertNotNull($tarea);
        $this->assertSame('40 3 * * *', $tarea->expression);
    }

    public function test_las_copias_de_seguridad_incluyen_los_disenos(): void
    {
        $incluidas = config('backup.backup.source.files.include');

        $this->assertTrue(collect($incluidas)->contains(fn ($ruta) => str_starts_with(config('filesystems.disks.disenos.root'), $ruta)));
    }

    private function diseno(int $hace, ?int $cliente = null): Diseno
    {
        $uuid = (string) Str::uuid();
        $ruta = "2026/11/{$uuid}.png";
        $miniatura = "2026/11/{$uuid}-mini.webp";
        Storage::disk('disenos')->put($ruta, 'png');
        Storage::disk('disenos')->put($miniatura, 'webp');

        $diseno = Diseno::create([
            'uuid' => $uuid, 'cliente_id' => $cliente, 'propietario' => $cliente ? null : hash('sha256', $uuid),
            'nombre_original' => 'logo.png', 'ruta' => $ruta, 'miniatura' => $miniatura, 'bytes' => 3, 'sha256' => hash('sha256', $uuid),
            'ancho_px' => 600, 'alto_px' => 600, 'util_x' => 0, 'util_y' => 0, 'util_ancho_px' => 600, 'util_alto_px' => 600,
            'fondo' => 'transparente', 'profundidad_bits' => 8, 'derechos_declarados_en' => now(), 'derechos_texto' => 'Declaro…',
        ]);
        $diseno->forceFill(['created_at' => now()->subDays($hace)])->saveQuietly();

        return $diseno;
    }
}
