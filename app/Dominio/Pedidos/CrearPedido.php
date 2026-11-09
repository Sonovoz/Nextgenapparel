<?php

namespace App\Dominio\Pedidos;

use App\Dominio\Carrito\TipoLinea;
use App\Dominio\Carrito\ValorarCarrito;
use App\Dominio\Clientes\TipoCliente;
use App\Dominio\Disenos\GuardarDiseno;
use App\Dominio\Precios\Motor\Destino;
use App\Dominio\Precios\Motor\LineaCalculada;
use App\Jobs\GenerarMaquetasPedido;
use App\Models\Carrito;
use App\Models\Cliente;
use App\Models\Cupon;
use App\Models\ImpresionCarrito;
use App\Models\LineaCarrito;
use App\Models\LineaPack;
use App\Models\LineaPedido;
use App\Models\Pedido;
use App\Models\Talla;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

/**
 * Convierte el carrito en un pedido (paso 6.1, D169), todo o nada:
 *
 * - Vuelve a valorar el carrito con el motor en ese instante (nunca se fía del total de
 *   la sesión) y no crea nada si el total no es el que el cliente vio al confirmar.
 * - Bloquea el carrito (un doble clic no crea dos pedidos) y el cupón (sus usos no se
 *   pasan del máximo con dos pedidos a la vez); los cupones que ya usó salen de sus pedidos.
 * - Copia cada diseño al disco privado «pedidos»: lo que se imprime no depende de que el
 *   cliente lo quite de su biblioteca ni de la limpieza de los diseños sin pedido (D150).
 * - Un invitado queda sin ficha hasta que confirma su correo (ConfirmarCorreoPedido).
 * - Vacía el carrito.
 */
final class CrearPedido
{
    public function __construct(private readonly ValorarCarrito $valorar) {}

    /**
     * @param  array<string, mixed>  $datos  los datos validados del cierre (D166): dirección de envío y, sin cuenta, correo, tipo, NIF y recargo
     */
    public function __invoke(Carrito $carrito, array $datos, ?Cliente $cliente, int $totalVisto, string $idioma, ?string $ip = null): Pedido
    {
        $uuid = (string) Str::uuid();

        try {
            // Hasta 3 intentos si MariaDB detecta un interbloqueo entre dos pedidos (M1).
            $pedido = DB::transaction(fn () => $this->crear($carrito, $datos, $cliente, $totalVisto, $idioma, $ip, $uuid), 3);
        } catch (Throwable $e) {
            Storage::disk(Pedido::DISCO)->deleteDirectory($uuid); // nada a medias
            throw $e;
        }
        // Las maquetas, en la cola con reintentos (paso 6.2): si fallan, el pedido sigue adelante.
        GenerarMaquetasPedido::dispatch($pedido->id);

        return $pedido;
    }

    private function crear(Carrito $carrito, array $datos, ?Cliente $cliente, int $totalVisto, string $idioma, ?string $ip, string $uuid): Pedido
    {
        Storage::disk(Pedido::DISCO)->deleteDirectory($uuid); // lo de un intento anterior
        // Primero los bloqueos y después cualquier lectura normal (revisión N3 del 6.1, A1):
        // en MariaDB la primera lectura sin bloqueo fija la foto de los datos de toda la
        // transacción; si se hiciera antes de esperar al cupón, no vería el pedido que acaba
        // de gastar su último uso ni el de este mismo correo.
        $carrito = Carrito::whereKey($carrito->getKey())->lockForUpdate()->first();
        if ($carrito === null) {
            throw new PedidoNoCreado('vacio');
        }
        $cupon = blank($carrito->cupon) ? null : Cupon::where('codigo', Cupon::normalizar($carrito->cupon))->lockForUpdate()->first();
        if (! $carrito->lineas()->exists()) {
            throw new PedidoNoCreado('vacio');
        }
        $correo = $cliente !== null ? $cliente->correo : mb_strtolower(trim((string) $datos['correo']));
        $usados = CuponesUsados::de($cliente, $correo);
        $recargo = $cliente !== null ? (bool) $cliente->recargo_equivalencia : (bool) ($datos['recargo'] ?? false);

        $valoracion = ($this->valorar)($carrito, new Destino((string) $datos['pais'], (string) $datos['codigo_postal']), $recargo, $usados);
        if (! $valoracion->confirmable()) {
            throw new PedidoNoCreado('no_confirmable');
        }
        $resultado = $valoracion->resultado;
        if ($resultado->total() !== $totalVisto) {
            throw new PedidoNoCreado('total_cambiado');
        }
        // Y el cupón, sobre su fila bloqueada: el último uso no se gasta dos veces (A1).
        if ($resultado->cupon !== null && ($cupon === null || ($cupon->usos_maximos !== null && $cupon->usos >= $cupon->usos_maximos))) {
            throw new PedidoNoCreado('total_cambiado');
        }

        $tipo = $cliente !== null ? $cliente->tipo : TipoCliente::from((string) $datos['tipo']);
        $direccion = collect(['nombre', 'empresa', 'direccion', 'direccion_2', 'codigo_postal', 'localidad', 'provincia', 'pais', 'telefono'])
            ->mapWithKeys(fn ($c) => [$c => blank($datos[$c] ?? null) ? null : trim((string) $datos[$c])])->all();

        $pedido = Pedido::create([
            'uuid' => $uuid,
            // Con cuenta, su ficha (el correo está verificado: lo exige el cierre). Sin cuenta, al confirmar el correo.
            'cliente_id' => $cliente?->id,
            'estado' => EstadoPedido::PendientePago,
            'idioma' => $idioma,
            'correo' => $correo,
            'correo_confirmado_en' => $cliente !== null ? now() : null,
            'tipo_cliente' => $tipo,
            'nombre_fiscal' => $cliente !== null ? $cliente->nombre : ($tipo !== TipoCliente::Particular && filled($direccion['empresa']) ? $direccion['empresa'] : $direccion['nombre']),
            'nif' => $cliente !== null ? $cliente->nif : (blank($datos['nif'] ?? null) ? null : $datos['nif']),
            'recargo_equivalencia' => $recargo,
            'direccion_envio' => $direccion,
            'direccion_facturacion' => $direccion, // facturación = envío (D166)
            'cupon' => $resultado->cupon !== null ? Cupon::normalizar((string) $carrito->cupon) : null,
            'bruto' => $resultado->bruto(),
            'descuentos' => $resultado->descuentos(),
            'subtotal' => $resultado->subtotal(),
            'envio' => $resultado->envioImporte(),
            'base_imponible' => $resultado->baseImponible(),
            'impuestos' => $resultado->totalImpuestos(),
            'total' => $resultado->total(),
            'desglose' => $resultado->aArray(),
            'condiciones_aceptadas_en' => now(),
            'condiciones_texto' => __('pedidos.condiciones_acepto', [], $idioma),
            'ip' => $ip,
        ]);

        // El motor devuelve primero las prendas y luego los packs, cada grupo en el orden del carrito.
        $lineas = $carrito->lineas;
        $ordenadas = $lineas->where('tipo', TipoLinea::Prenda)->values()->concat($lineas->where('tipo', TipoLinea::Pack)->values());
        if ($ordenadas->count() !== count($resultado->lineas)) {
            throw new LogicException('El motor no ha valorado todas las líneas del carrito.');
        }
        foreach ($ordenadas as $i => $linea) {
            $calculada = $resultado->lineas[$i];
            // Cada línea del motor es la de esta línea del carrito (B2).
            if ($calculada->tipo !== $linea->tipo->value || $calculada->referencia !== ($linea->tipo === TipoLinea::Pack ? $linea->pack_id : $linea->prenda_id)) {
                throw new LogicException('Las líneas del motor no corresponden a las del carrito.');
            }
            $this->copiarLinea($pedido, $linea, $calculada, $lineas->search(fn ($l) => $l->is($linea)));
        }

        if ($cupon !== null && $resultado->cupon !== null) {
            $cupon->increment('usos');
        }
        $pedido->historial()->create(['estado' => EstadoPedido::PendientePago, 'nota' => 'Pedido creado desde la tienda']);
        // Fecha estimada de entrega (ficha § 8): familia más lenta + unidades + transportista.
        $familias = $pedido->lineas()->with('prenda.familia')->get()->flatMap(fn ($l) => $l->prenda !== null ? [$l->prenda->familia] : ($l->pack_id === null ? [] : LineaPack::where('pack_id', $l->pack_id)->with('prenda.familia')->get()->map(fn ($lp) => $lp->prenda?->familia)))
            ->filter()->map(fn ($f) => (int) $f->plazo_base_dias)->values()->all();
        $pedido->update(['fecha_estimada_entrega' => PlazoEntrega::fecha($familias, $resultado->unidades(), (int) ($resultado->envio['plazo_dias'] ?? 0), now())]);
        $carrito->delete(); // con sus líneas e impresiones
        // El número, lo último: el contador queda bloqueado solo un instante (M2).
        $pedido->update(['numero' => Numeracion::numeroPedido(Numeracion::siguiente('pedido'))]);

        return $pedido;
    }

    private function copiarLinea(Pedido $pedido, LineaCarrito $linea, LineaCalculada $calculada, int $orden): void
    {
        $esPack = $linea->tipo === TipoLinea::Pack;
        $tallas = null;
        if (! $esPack) {
            $unidades = $linea->unidadesPorTalla();
            $tallas = Talla::whereIn('id', array_keys($unidades))->orderBy('orden')->get()
                ->map(fn (Talla $t) => ['talla_id' => $t->id, 'nombre' => $t->nombre, 'unidades' => $unidades[$t->id]])->values()->all();
        }

        $copia = $pedido->lineas()->create([
            'orden' => $orden,
            'tipo' => $linea->tipo->value,
            'prenda_id' => $linea->prenda_id,
            'color_id' => $linea->color_id,
            'pack_id' => $linea->pack_id,
            'nombre' => $esPack ? $linea->pack->getTranslations('nombre') : $linea->prenda->getTranslations('nombre'),
            'color_nombre' => $linea->color?->getTranslations('nombre'),
            'color_hex' => $linea->color?->codigo_hex,
            'tallas' => $tallas,
            'elecciones' => $linea->elecciones,
            'unidades' => $calculada->unidades,
            'prendas' => $calculada->prendas,
            'impresion' => $calculada->impresion,
            'descuento' => $calculada->descuento,
            'cupon' => $calculada->cupon,
            'neto' => $calculada->neto(),
            'tipo_iva' => $calculada->tipoIva,
            'recargo' => $calculada->recargo,
        ]);

        foreach ($linea->impresiones as $impresion) {
            $this->copiarImpresion($pedido, $copia, $impresion);
        }
    }

    private function copiarImpresion(Pedido $pedido, LineaPedido $linea, ImpresionCarrito $impresion): void
    {
        $diseno = $impresion->diseno;
        $archivo = $miniatura = null;
        $calidad = null;
        if ($diseno !== null) {
            $origen = Storage::disk(GuardarDiseno::DISCO);
            $destino = Storage::disk(Pedido::DISCO);
            $base = "{$pedido->uuid}/{$linea->uuid}-{$impresion->zona_impresion_id}";
            $archivo = "{$base}.png";
            $destino->writeStream($archivo, $origen->readStream($diseno->ruta));
            if ($diseno->miniatura !== null && $origen->exists($diseno->miniatura)) {
                $miniatura = "{$base}-mini.webp";
                $destino->writeStream($miniatura, $origen->readStream($diseno->miniatura));
            }
            $calidad = $diseno->calidad($impresion->ancho_mm, $impresion->alto_mm);
        }

        $linea->impresiones()->create([
            'zona_impresion_id' => $impresion->zona_impresion_id,
            'zona_nombre' => $impresion->zona->getTranslations('nombre'),
            'cara' => $impresion->zona->cara?->value,
            'diseno_id' => $diseno?->id,
            'archivo' => $archivo,
            'miniatura' => $miniatura,
            'nombre_original' => $diseno?->nombre_original,
            'sha256' => $diseno?->sha256,
            'util_x' => $diseno?->util_x,
            'util_y' => $diseno?->util_y,
            'util_ancho_px' => $diseno?->util_ancho_px,
            'util_alto_px' => $diseno?->util_alto_px,
            'ppp' => $calidad['ppp'] ?? null,
            'nivel_calidad' => isset($calidad['nivel']) ? $calidad['nivel']->value : null,
            'texto' => $impresion->texto,
            'fuente' => $impresion->fuente,
            'tinta' => $impresion->tinta,
            'ancho_mm' => $impresion->ancho_mm,
            'alto_mm' => $impresion->alto_mm,
            'desde_cuello_mm' => $impresion->desde_cuello_mm,
            'desde_centro_mm' => $impresion->desde_centro_mm,
        ]);
    }
}
