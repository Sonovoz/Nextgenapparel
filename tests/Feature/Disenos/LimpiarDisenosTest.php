<?php

namespace Tests\Feature\Disenos;

use App\Dominio\Disenos\GuardarDiseno;
use App\Dominio\Disenos\LimpiarDisenos;
use App\Models\Ajuste;
use App\Models\Carrito;
use App\Models\Cliente;
use App\Models\Diseno;
use App\Models\ZonaImpresion;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Retención de los diseños (paso 4.6, D230): sin venta, se borran pasadas las horas de Ajustes. */
class LimpiarDisenosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(GuardarDiseno::DISCO);
    }

    /** D230: sin venta, de cualquiera (también con cuenta), se borran a las 72 h; en un carrito usado hace poco, esperan. */
    public function test_borra_los_que_no_se_venden_a_las_72_horas_y_conserva_los_que_estan_en_uso(): void
    {
        Ajuste::guardar('disenos.horas_sin_venta', 72);
        $caducado = $this->diseno(horas: 73);
        $reciente = $this->diseno(horas: 71);
        $deCliente = $this->diseno(horas: 400, cliente: Cliente::factory()->create()->id);
        $enCarrito = $this->diseno(horas: 100);
        $linea = Carrito::create()->lineas()->create(['tipo' => 'prenda', 'tallas' => []]);
        $linea->impresiones()->create(['diseno_id' => $enCarrito->id, 'zona_impresion_id' => ZonaImpresion::factory()->create()->id, 'ancho_mm' => 100, 'alto_mm' => 100, 'desde_cuello_mm' => 80, 'desde_centro_mm' => 0]);

        $this->artisan('disenos:limpiar')->expectsOutputToContain('Diseños borrados: 2.')->assertSuccessful();

        $this->assertModelMissing($caducado);
        $this->assertFalse(Storage::disk('disenos')->exists($caducado->ruta));
        $this->assertFalse(Storage::disk('disenos')->exists($caducado->miniatura));
        $this->assertModelMissing($deCliente); // también de «Mis diseños»
        $this->assertModelExists($reciente);
        $this->assertModelExists($enCarrito); // el carrito se acaba de usar
        $this->assertDatabaseHas('activity_log', ['log_name' => 'disenos', 'description' => 'Limpieza de diseños']);

        // Con el carrito abandonado más allá del plazo, también se borra.
        Carrito::query()->update(['updated_at' => now()->subHours(80)]);
        (new LimpiarDisenos)();
        $this->assertModelMissing($enCarrito);
    }

    public function test_las_horas_salen_de_los_ajustes(): void
    {
        Ajuste::guardar('disenos.horas_sin_venta', 5);
        $diseno = $this->diseno(horas: 6);

        (new LimpiarDisenos)();

        $this->assertModelMissing($diseno);
    }

    public function test_borra_los_archivos_huerfanos_de_mas_de_un_dia_y_no_los_de_una_subida_en_curso(): void
    {
        $diseno = $this->diseno(horas: 1);
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
        $this->diseno(horas: 1);

        (new LimpiarDisenos)();

        $this->assertDatabaseMissing('activity_log', ['description' => 'Limpieza de diseños']);
    }

    public function test_se_ejecuta_cada_hora_y_la_de_los_pedidos_cada_noche(): void
    {
        $tareas = collect(app(Schedule::class)->events());

        $this->assertSame('40 * * * *', $tareas->first(fn ($e) => str_contains($e->command ?? '', 'disenos:limpiar'))?->expression);
        $this->assertSame('50 3 * * *', $tareas->first(fn ($e) => str_contains($e->command ?? '', 'pedidos:limpiar-archivos'))?->expression);
    }

    public function test_las_copias_de_seguridad_incluyen_los_disenos(): void
    {
        $incluidas = config('backup.backup.source.files.include');

        $this->assertTrue(collect($incluidas)->contains(fn ($ruta) => str_starts_with(config('filesystems.disks.disenos.root'), $ruta)));
    }

    private function diseno(int $horas, ?int $cliente = null): Diseno
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
        $diseno->forceFill(['created_at' => now()->subHours($horas)])->saveQuietly();

        return $diseno;
    }
}
