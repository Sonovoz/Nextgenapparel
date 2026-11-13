<?php

namespace Tests\Feature\Sistema;

use App\Dominio\Disenos\GuardarDiseno;
use App\Dominio\Pedidos\EstadoPedido as E;
use App\Dominio\Sistema\Acceso\Rol;
use App\Dominio\Sistema\Almacenamiento\Almacenamiento as Medida;
use App\Dominio\Sistema\Salud\Comprobaciones\Almacenamiento as ComprobacionAlmacenamiento;
use App\Dominio\Sistema\Salud\Comprobaciones\Limpieza;
use App\Dominio\Sistema\Salud\EstadoSalud;
use App\Filament\Pages\Almacenamiento;
use App\Models\Ajuste;
use App\Models\Carrito;
use App\Models\Diseno;
use App\Models\Pedido;
use App\Models\ZonaImpresion;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** D231: Herramientas → Almacenamiento y las reglas de salud del espacio y de la limpieza. */
class AlmacenamientoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(GuardarDiseno::DISCO);
        Storage::fake(Pedido::DISCO);
    }

    private function diseno(int $horas, int $bytes = 3): Diseno
    {
        $uuid = (string) Str::uuid();
        Storage::disk(GuardarDiseno::DISCO)->put("2026/11/{$uuid}.png", 'png');
        $diseno = Diseno::create(['uuid' => $uuid, 'propietario' => hash('sha256', $uuid), 'nombre_original' => 'logo.png', 'ruta' => "2026/11/{$uuid}.png",
            'bytes' => $bytes, 'sha256' => hash('sha256', $uuid), 'ancho_px' => 600, 'alto_px' => 600, 'util_x' => 0, 'util_y' => 0, 'util_ancho_px' => 600,
            'util_alto_px' => 600, 'fondo' => 'transparente', 'profundidad_bits' => 8, 'derechos_declarados_en' => now(), 'derechos_texto' => 'Declaro…']);
        $diseno->forceFill(['created_at' => now()->subHours($horas)])->saveQuietly();

        return $diseno;
    }

    public function test_el_espacio_del_proyecto_avisa_y_falla_segun_el_maximo_elegido(): void
    {
        Ajuste::guardar('almacenamiento.limite_gb', 1);
        $this->diseno(horas: 1, bytes: 850 * 1024 ** 2);
        Medida::medir(recalcular: true);

        $partes = collect(Medida::medir())->keyBy('clave');
        $this->assertSame(850 * 1024 ** 2, $partes['disenos_sin_venta']['bytes']);
        $this->assertSame(EstadoSalud::Aviso, (new ComprobacionAlmacenamiento)->comprobar()[0]); // 83 %

        $this->diseno(horas: 1, bytes: 150 * 1024 ** 2);
        Medida::medir(recalcular: true);
        [$estado, $mensaje] = (new ComprobacionAlmacenamiento)->comprobar();
        $this->assertSame(EstadoSalud::Fallo, $estado); // 97 %
        $this->assertStringContainsString('Borra archivos sin venta', $mensaje);

        Ajuste::guardar('almacenamiento.limite_gb', 20);
        $this->assertSame(EstadoSalud::Correcto, (new ComprobacionAlmacenamiento)->comprobar()[0]);
    }

    public function test_la_salud_avisa_si_las_limpiezas_no_se_ejecutan(): void
    {
        CarbonImmutable::setTestNow(now()->addDays(3)); // los latidos de otras pruebas quedan atrás
        $this->assertSame(EstadoSalud::Aviso, (new Limpieza)->comprobar()[0]);

        $this->artisan('disenos:limpiar')->assertSuccessful();
        $this->artisan('pedidos:limpiar-archivos')->assertSuccessful();

        $this->assertSame(EstadoSalud::Correcto, (new Limpieza)->comprobar()[0]);
    }

    public function test_el_backend_elige_el_maximo_borra_sin_venta_y_descarga_el_zip_de_originales(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::Superadministrador));
        $this->get(Almacenamiento::getUrl())->assertOk()->assertSee('Espacio del proyecto')->assertSee('Diseños sin venta');

        Livewire::test(Almacenamiento::class)->callAction('limite', ['limite_gb' => 50])->assertHasNoActionErrors();
        $this->assertSame(50, (int) Ajuste::obtener('almacenamiento.limite_gb'));

        // «Borrar archivos sin venta» con los recientes: se borra el de hace 1 h, no el de un carrito en uso.
        $reciente = $this->diseno(horas: 1);
        $enCarrito = $this->diseno(horas: 1);
        Carrito::create()->lineas()->create(['tipo' => 'prenda', 'tallas' => []])->impresiones()->create(['diseno_id' => $enCarrito->id,
            'zona_impresion_id' => ZonaImpresion::factory()->create()->id, 'ancho_mm' => 100, 'alto_mm' => 100, 'desde_cuello_mm' => 80, 'desde_centro_mm' => 0]);
        Livewire::test(Almacenamiento::class)->callAction('borrar', ['recientes' => true])->assertNotified('Borrados 1 diseños sin venta');
        $this->assertModelMissing($reciente);
        $this->assertModelExists($enCarrito);

        // ZIP de los originales de un pedido que se borran en los próximos días.
        CarbonImmutable::setTestNow('2026-11-13 10:00:00');
        $pedido = Pedido::factory()->create(['estado' => E::Entregado, 'numero' => 'PED-0007']);
        $pedido->forceFill(['created_at' => now()->subWeeks(19)])->saveQuietly();
        $pedido->historial()->create(['estado' => E::Enviado->value])->forceFill(['created_at' => now()->subWeeks(17)->subDays(3)])->saveQuietly();
        Storage::disk(Pedido::DISCO)->put("{$pedido->uuid}/logo.png", 'PNG');
        $pedido->lineas()->create(['tipo' => 'prenda', 'nombre' => ['es' => 'Camiseta'], 'unidades' => 10, 'prendas' => 1000, 'impresion' => 500,
            'descuento' => 0, 'cupon' => 0, 'neto' => 1500, 'tipo_iva' => 2100, 'recargo' => 0])
            ->impresiones()->create(['zona_nombre' => ['es' => 'Frontal'], 'archivo' => "{$pedido->uuid}/logo.png", 'nombre_original' => 'logo.png',
                'ancho_mm' => 100, 'alto_mm' => 100, 'desde_cuello_mm' => 75, 'desde_centro_mm' => 0]);

        Livewire::test(Almacenamiento::class)->callAction('zip')->assertFileDownloaded('originales-pedidos-2026-11-13.zip');
    }
}
