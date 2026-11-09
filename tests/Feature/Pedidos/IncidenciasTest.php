<?php

namespace Tests\Feature\Pedidos;

use App\Dominio\Facturacion\EmitirFactura;
use App\Dominio\Facturacion\TipoFactura;
use App\Dominio\Incidencias\IncidenciaNoValida;
use App\Dominio\Incidencias\MotivoIncidencia;
use App\Dominio\Incidencias\ResolucionIncidencia;
use App\Dominio\Incidencias\ResolverIncidencia;
use App\Dominio\Pedidos\EstadoPedido as E;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Resources\Incidencias\IncidenciaResource;
use App\Filament\Resources\Incidencias\Pages\ViewIncidencia;
use App\Http\Controllers\PedidoController;
use App\Models\Ajuste;
use App\Models\Incidencia;
use App\Models\Pedido;
use App\Notifications\Pedidos\IncidenciaAbierta;
use App\Notifications\Pedidos\IncidenciaResuelta;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Imagick;
use ImagickPixel;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Incidencias con fotos (paso 6.6b, D178). Pedido entregado y facturado: 105,00 € + envío 4,90 €,
 * base 109,90 €, IVA 23,08 €, total 132,98 €.
 */
class IncidenciasTest extends TestCase
{
    use RefreshDatabase;

    private Pedido $pedido;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake(Pedido::DISCO);
        Ajuste::guardar('facturacion.emisor', ['razon_social' => 'DTI Textil SL', 'nif' => 'B12345674', 'direccion' => 'Calle Industria 4', 'codigo_postal' => '28001', 'localidad' => 'Madrid']);
        Ajuste::guardar('tienda.correo_contacto', 'taller@nextgenapparel.test');

        $this->pedido = Pedido::factory()->create(['estado' => E::Entregado, 'numero' => 'PED-0042', 'impuestos' => 2308, 'total' => 13298, 'correo_confirmado_en' => now(),
            'desglose' => ['pasos' => [[], [], [], [], [], ['paso' => 'impuestos', 'detalle' => [['tipo_iva' => 2100, 'recargo' => 0, 'base' => 10990, 'cuota' => 2308, 'cuota_recargo' => 0]]]]]]);
        $this->pedido->lineas()->create(['tipo' => 'prenda', 'nombre' => ['es' => 'Camiseta Essential'], 'tallas' => [['talla_id' => 1, 'nombre' => 'M', 'unidades' => 15]],
            'unidades' => 15, 'prendas' => 6000, 'impresion' => 4500, 'descuento' => 0, 'cupon' => 0, 'neto' => 10500, 'tipo_iva' => 2100, 'recargo' => 0]);
        app(EmitirFactura::class)->delPedido($this->pedido);
        $this->pedido->historial()->create(['estado' => E::Entregado]);
    }

    /** Una foto con un comentario escondido, para ver que se quitan los metadatos. */
    private static function foto(string $nombre = 'foto.png', int $ancho = 2400): UploadedFile
    {
        $imagen = new Imagick;
        $imagen->newImage($ancho, 1200, new ImagickPixel('#c33'));
        $imagen->setImageProperty('comment', 'Calle Secreta 1, 40.4168 -3.7038');
        // La ubicación también puede ir en un perfil XMP (como la guardan muchos móviles).
        $imagen->setImageProfile('xmp', '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"><rdf:Description xmlns:exif="http://ns.adobe.com/exif/1.0/" exif:GPSLatitude="40,25.0N" exif:GPSLongitude="3,42.2W"/></rdf:RDF></x:xmpmeta>');
        $imagen->setImageFormat('png');

        return UploadedFile::fake()->createWithContent($nombre, $imagen->getImageBlob());
    }

    private function abrir(array $datos = [], ?array $fotos = null): TestResponse
    {
        return $this->withSession([PedidoController::SESION => [$this->pedido->uuid]])
            ->post(route('pedidos.es.incidencia', ['pedido' => $this->pedido->uuid]), [
                ...['motivo' => 'impresion', 'descripcion' => 'El logo del frontal salió desplazado en 3 camisetas.'], ...$datos,
                'fotos' => $fotos ?? [self::foto()],
            ]);
    }

    public function test_el_cliente_abre_una_incidencia_con_fotos_sin_metadatos(): void
    {
        $this->abrir(fotos: [self::foto(), self::foto('otra.png', 800)])
            ->assertRedirect(PedidoController::url('ver', $this->pedido).'#incidencias')->assertSessionHas('estado_pedido', __('incidencias.abierta_ok', ['codigo' => 'INC-0001']));

        $incidencia = Incidencia::sole();
        $this->assertSame(['INC-0001', MotivoIncidencia::Impresion, 'abierta'], [$incidencia->codigo, $incidencia->motivo, $incidencia->estado]);
        $this->assertSame([[2000, 1000], [800, 1200]], $incidencia->fotos->map(fn ($f) => [$f->ancho_px, $f->alto_px])->all()); // la grande, reducida
        $guardada = new Imagick;
        $guardada->readImageBlob(Storage::disk(Pedido::DISCO)->get($incidencia->fotos[0]->ruta));
        $this->assertSame('WEBP', $guardada->getImageFormat());
        $this->assertArrayNotHasKey('comment', $guardada->getImageProperties());
        $this->assertSame([], $guardada->getImageProfiles());
        $this->assertSame([], array_filter(array_keys($guardada->getImageProperties()), fn ($k) => str_starts_with($k, 'exif:') || str_starts_with($k, 'xmp')));
        Notification::assertSentOnDemand(IncidenciaAbierta::class, fn ($n, $c, $destino) => $destino->routes['mail'] === 'taller@nextgenapparel.test');
        $this->assertStringContainsString('INC-0001', $this->pedido->notas()->sole()->texto);

        // Se ve en el pedido, con sus fotos solo para quien puede verlo.
        $foto = route('pedidos.es.foto-incidencia', ['pedido' => $this->pedido->uuid, 'foto' => $incidencia->fotos[0]->id]);
        $this->withSession([PedidoController::SESION => [$this->pedido->uuid]])->get(PedidoController::url('ver', $this->pedido))
            ->assertSee('INC-0001')->assertSee(__('incidencias.estado_abierta'))->assertSee($foto, false)->assertDontSee(__('incidencias.abrir'));
        $this->withSession([PedidoController::SESION => [$this->pedido->uuid]])->get($foto)->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->flushSession();
        $this->get($foto)->assertNotFound();

        // Con acceso a otro pedido, la foto de este por la URL de aquel tampoco.
        $propio = Pedido::factory()->create();
        $this->withSession([PedidoController::SESION => [$propio->uuid]])
            ->get(route('pedidos.es.foto-incidencia', ['pedido' => $propio->uuid, 'foto' => $incidencia->fotos[0]->id]))->assertNotFound();
    }

    public static function datosNoValidos(): array
    {
        return [
            'sin fotos si no es una prenda que falta' => [['motivo' => 'prenda'], [], 'incidencia', 'incidencias.faltan_fotos'],
            'descripción demasiado corta' => [['descripcion' => 'Mal'], null, 'incidencia', 'incidencias.descripcion_corta'],
            'más de 5 fotos' => [[], 'seis', 'fotos', null],
            'un archivo que no es una imagen' => [[], 'pdf', 'fotos.0', null],
            'un motivo que no existe' => [['motivo' => 'regalo'], null, 'motivo', null],
        ];
    }

    #[DataProvider('datosNoValidos')]
    public function test_lo_que_no_vale_se_explica_y_no_abre_nada(array $datos, array|string|null $fotos, string $campo, ?string $mensaje): void
    {
        $fotos = match ($fotos) {
            'seis' => array_map(fn ($i) => self::foto("f{$i}.png", 400), range(1, 6)),
            'pdf' => [UploadedFile::fake()->create('factura.pdf', 20, 'application/pdf')],
            default => $fotos,
        };

        $respuesta = $this->abrir($datos, $fotos)->assertSessionHasErrors($campo);
        if ($mensaje !== null) {
            $respuesta->assertSessionHasErrors([$campo => __($mensaje)]);
        }
        $this->assertSame(0, Incidencia::count());
        $this->assertSame([], Storage::disk(Pedido::DISCO)->allFiles("{$this->pedido->uuid}/incidencias"));
    }

    public function test_si_falta_una_prenda_no_hacen_falta_fotos(): void
    {
        $this->abrir(['motivo' => 'falta', 'descripcion' => 'Pedimos 15 y llegaron 14 camisetas.'], [])->assertSessionHasNoErrors();

        $this->assertSame(MotivoIncidencia::Falta, Incidencia::sole()->motivo);
    }

    public static function cuandoNo(): array
    {
        return [
            'sin pagar' => [E::PendientePago, 0],
            'pagado' => [E::Pagado, 0],
            'en producción' => [E::EnProduccion, 0],
            'cancelado' => [E::Cancelado, 0],
            'entregado hace 15 días (plazo 14)' => [E::Entregado, 15],
        ];
    }

    #[DataProvider('cuandoNo')]
    public function test_solo_se_abre_una_vez_enviado_y_dentro_del_plazo(E $estado, int $hace): void
    {
        $this->pedido->update(['estado' => $estado]);
        $this->pedido->historial()->update(['created_at' => now()->subDays($hace)]);

        $this->abrir()->assertSessionHasErrors(['incidencia' => __('incidencias.no_puede')]);
        $this->assertSame(0, Incidencia::count());
    }

    public function test_enviado_sin_marcar_la_entrega_cuenta_desde_el_envio_y_una_abierta_a_la_vez(): void
    {
        $this->pedido->historial()->delete();
        $this->pedido->update(['estado' => E::Enviado]);
        $this->pedido->historial()->create(['estado' => E::Enviado, 'created_at' => now()->subDays(3)]);

        $this->abrir()->assertSessionHasNoErrors();
        $this->abrir()->assertSessionHasErrors(['incidencia' => __('incidencias.no_puede')]);
        $this->assertSame(1, Incidencia::count());
    }

    /** Reembolso parcial: rectificativa parcial al momento, correo al cliente con ella y se ve en su pedido. */
    public function test_resolver_con_reembolso_emite_la_rectificativa_parcial(): void
    {
        $this->abrir();
        $incidencia = Incidencia::sole();
        $quien = $this->usuarioConRol(Rol::GestorTienda);

        app(ResolverIncidencia::class)($incidencia, ResolucionIncidencia::Reembolso, 'Te devolvemos 3 camisetas.', 2000, $quien);

        $incidencia->refresh();
        $this->assertSame(['resuelta', ResolucionIncidencia::Reembolso, 2000, $quien->id], [$incidencia->estado, $incidencia->resolucion, $incidencia->importe_reembolso, $incidencia->user_id]);
        $this->assertSame([TipoFactura::RectificativaSimplificada, true, -2000], [$incidencia->factura->tipo, $incidencia->factura->parcial, $incidencia->factura->total]);
        $this->assertSame('Reembolso parcial por la incidencia INC-0001', $incidencia->factura->lineas->sole()->descripcion);
        Notification::assertSentOnDemand(IncidenciaResuelta::class, fn ($n, $c, $destino) => $destino->routes['mail'] === $this->pedido->correo);
        $this->assertSame(11298, app(EmitirFactura::class)->pendienteDeRectificar($this->pedido));

        $this->withSession([PedidoController::SESION => [$this->pedido->uuid]])->get(PedidoController::url('ver', $this->pedido))
            ->assertSee('Te devolvemos 3 camisetas.')->assertSee($incidencia->factura->codigo)->assertSee('20,00');

        // Una sola vez.
        $this->expectException(IncidenciaNoValida::class);
        app(ResolverIncidencia::class)($incidencia, ResolucionIncidencia::Rechazo, 'Otra', null, $quien);
    }

    public function test_un_reembolso_mayor_que_lo_cobrado_no_resuelve_nada(): void
    {
        $this->abrir();
        $incidencia = Incidencia::sole();

        try {
            app(ResolverIncidencia::class)($incidencia, ResolucionIncidencia::Reembolso, 'Todo y más', 13299, $this->usuarioConRol(Rol::GestorTienda));
            $this->fail('No puede devolver más de lo cobrado.');
        } catch (IncidenciaNoValida $e) {
            $this->assertStringContainsString('132,98', $e->motivo);
        }
        $this->assertSame(['abierta', null], [$incidencia->fresh()->estado, $incidencia->fresh()->factura_id]);
        $this->assertSame(1, $this->pedido->facturas()->count());
    }

    public static function centimos(): array
    {
        return ['20' => ['20', 2000], '20,5' => ['20,5', 2050], '20.05' => ['20.05', 2005], '0,99' => ['0,99', 99], '132,98' => ['132,98', 13298]];
    }

    #[DataProvider('centimos')]
    public function test_los_euros_del_formulario_pasan_a_centimos_sin_redondeos(string $euros, int $centimos): void
    {
        $this->assertSame($centimos, ViewIncidencia::centimos($euros));
    }

    public function test_el_equipo_resuelve_desde_la_ficha_y_produccion_no_ve_las_incidencias(): void
    {
        Filament::setCurrentPanel('admin');
        $this->abrir();
        $incidencia = Incidencia::sole();

        $this->actingAs($this->usuarioConRol(Rol::Produccion));
        $this->get(IncidenciaResource::getUrl('index'))->assertForbidden();
        $this->get(route('backend.pedidos.foto-incidencia', ['pedido' => $this->pedido->uuid, 'foto' => $incidencia->fotos[0]->id]))->assertNotFound();

        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));
        $this->get(IncidenciaResource::getUrl('index'))->assertOk()->assertSee('INC-0001');
        $this->get(route('backend.pedidos.foto-incidencia', ['pedido' => $this->pedido->uuid, 'foto' => $incidencia->fotos[0]->id]))->assertOk();
        Livewire::test(ViewIncidencia::class, ['record' => $incidencia->uuid])
            ->callAction('resolver', ['resolucion' => 'reembolso', 'importe' => '132,99', 'respuesta' => 'Más de lo cobrado'])
            ->assertHasActionErrors(['importe']);
        $this->assertTrue($incidencia->fresh()->abierta());
        Livewire::test(ViewIncidencia::class, ['record' => $incidencia->uuid])
            ->callAction('resolver', ['resolucion' => 'reembolso', 'importe' => '20,50', 'respuesta' => 'Lo sentimos: te devolvemos parte.'])
            ->assertHasNoActionErrors();

        $this->assertSame([ResolucionIncidencia::Reembolso, 2050], [$incidencia->fresh()->resolucion, $incidencia->fresh()->importe_reembolso]);
        Livewire::test(ViewIncidencia::class, ['record' => $incidencia->uuid])->assertActionHidden('resolver');
    }

    public function test_rechazar_no_emite_factura(): void
    {
        $this->abrir();
        app(ResolverIncidencia::class)(Incidencia::sole(), ResolucionIncidencia::Rechazo, 'Las fotos no muestran ningún defecto.', null, $this->usuarioConRol(Rol::GestorTienda));

        $this->assertSame([ResolucionIncidencia::Rechazo, null, null], [Incidencia::sole()->resolucion, Incidencia::sole()->factura_id, Incidencia::sole()->importe_reembolso]);
        $this->assertSame(1, $this->pedido->facturas()->count());
    }
}
