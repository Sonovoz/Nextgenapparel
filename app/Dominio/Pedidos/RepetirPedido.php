<?php

namespace App\Dominio\Pedidos;

use App\Dominio\Carrito\EditarCarrito;
use App\Dominio\Carrito\LineaNoValida;
use App\Dominio\Disenos\DisenoNoValido;
use App\Dominio\Disenos\Dueno;
use App\Dominio\Disenos\GuardarDiseno;
use App\Models\Carrito;
use App\Models\ImpresionPedido;
use App\Models\LineaPedido;
use App\Models\Pedido;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * «Repetir pedido» (paso 6.6, D153, D177): pone en el carrito las mismas prendas, colores, tallas
 * y diseños, en el mismo sitio. Pasa por las mismas comprobaciones que añadirlas a mano, y el
 * precio lo calcula el motor de nuevo en el carrito (regla 3): puede no ser el del pedido.
 *
 * Los logos salen de la copia del pedido y vuelven a la biblioteca de quien repite (si ya los
 * tiene, se reutilizan por su huella). Una línea que ya no se puede vender (prenda retirada,
 * zona quitada, pack caducado…) se salta y se dice cuál.
 */
final class RepetirPedido
{
    public function __construct(private readonly EditarCarrito $editar, private readonly GuardarDiseno $guardarDiseno) {}

    /** @return array{anadidas: int, omitidas: list<string>} */
    public function __invoke(Pedido $pedido, Carrito $carrito, Dueno $dueno): array
    {
        $anadidas = 0;
        $omitidas = [];
        foreach ($pedido->load('lineas.impresiones')->lineas as $linea) {
            try {
                $impresiones = $this->impresiones($linea, $dueno);
                if ($linea->tipo === 'pack' && $linea->pack_id !== null) {
                    $elecciones = array_map(fn (array $e) => ['color' => (int) ($e['color'] ?? 0), 'tallas' => array_map('intval', (array) ($e['tallas'] ?? []))], (array) $linea->elecciones);
                    $this->editar->anadirPack($carrito, $dueno, $linea->pack_id, $elecciones, $impresiones);
                } elseif ($linea->prenda_id !== null && $linea->color_id !== null) {
                    $tallas = collect($linea->tallas ?? [])->mapWithKeys(fn (array $t) => [(int) $t['talla_id'] => (int) $t['unidades']])->all();
                    $this->editar->anadirPrenda($carrito, $dueno, $linea->prenda_id, $linea->color_id, $tallas, $impresiones);
                } else {
                    throw new LineaNoValida(['no_disponible']);
                }
                $anadidas++;
            } catch (LineaNoValida|DisenoNoValido) {
                $omitidas[] = $linea->nombreEn().($linea->colorEn() ? ' · '.$linea->colorEn() : '');
            }
        }

        return ['anadidas' => $anadidas, 'omitidas' => $omitidas];
    }

    /** @return array<int, array<string, mixed>> zona_id => impresión, como las envía el configurador */
    private function impresiones(LineaPedido $linea, Dueno $dueno): array
    {
        $impresiones = [];
        foreach ($linea->impresiones as $impresion) {
            if ($impresion->zona_impresion_id === null) {
                throw new LineaNoValida(['zona_no_disponible']); // la zona ya no existe
            }
            $medidas = ['ancho_mm' => $impresion->ancho_mm, 'alto_mm' => $impresion->alto_mm, 'desde_cuello_mm' => $impresion->desde_cuello_mm, 'desde_centro_mm' => $impresion->desde_centro_mm];
            $impresiones[$impresion->zona_impresion_id] = $impresion->texto !== null
                ? ['texto' => $impresion->texto, 'fuente' => (int) $impresion->fuente, 'tinta' => (string) $impresion->tinta, ...$medidas]
                : ['diseno' => $this->diseno($impresion, $dueno), ...$medidas];
        }

        return $impresiones;
    }

    /** El uuid del diseño en la biblioteca de quien repite, a partir de la copia del pedido. */
    private function diseno(ImpresionPedido $impresion, Dueno $dueno): string
    {
        $disco = Storage::disk(Pedido::DISCO);
        if ($impresion->archivo === null || ! $disco->exists($impresion->archivo)) {
            throw new LineaNoValida(['diseno_no_disponible']);
        }
        $temporal = (string) tempnam(sys_get_temp_dir(), 'repetir');
        try {
            file_put_contents($temporal, $disco->get($impresion->archivo));

            return ($this->guardarDiseno)(new UploadedFile($temporal, $impresion->nombre_original ?? 'diseno.png', 'image/png', null, true), $dueno)->uuid;
        } finally {
            @unlink($temporal);
        }
    }
}
