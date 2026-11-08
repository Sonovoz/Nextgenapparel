<?php

namespace App\Http\Controllers;

use App\Dominio\Carrito\CarritoActual;
use App\Dominio\Carrito\ValorarCarrito;
use App\Dominio\Clientes\Nif;
use App\Dominio\Clientes\Paises;
use App\Dominio\Clientes\ReglasDireccion;
use App\Dominio\Clientes\TipoCliente;
use App\Dominio\Clientes\TipoDireccion;
use App\Dominio\Pedidos\CuponesUsados;
use App\Dominio\Precios\Motor\Destino;
use App\Models\Ajuste;
use App\Models\Cliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «Cierra el pedido» hasta el total (paso 5.8, D166). Con cuenta, los datos fiscales
 * salen de su ficha (se cambian en «Mis datos») y la dirección de envío se rellena con la
 * predeterminada; sin cuenta, el invitado lo escribe todo en un formulario. Facturación
 * = envío. Con el destino, el motor calcula envío, IVA, recargo y total. Confirmar y
 * pagar llegan con los pedidos (fase 6) y Redsys (fase 7); el correo del invitado se
 * confirmará entonces con un enlace.
 */
class CierreController
{
    public const SESION = 'cierre';

    public function ver(Request $request, ValorarCarrito $valorar): View|RedirectResponse
    {
        $carrito = CarritoActual::de($request);
        if ($carrito === null || ! $carrito->lineas()->exists()) {
            return redirect(CarritoController::url('ver'));
        }
        $cliente = $this->cliente($request);
        // Los datos calculados valen solo para quien los calculó (revisión N3 del 5.8, M2):
        // si un invitado entra en su cuenta, se descartan y se usan los de su ficha.
        $datos = $request->session()->get(self::SESION);
        if ($datos !== null && ($datos['cliente_id'] ?? null) !== $cliente?->id) {
            $request->session()->forget(self::SESION);
            $datos = null;
        }
        if ($datos !== null && $cliente !== null) {
            $datos['recargo'] = (bool) $cliente->recargo_equivalencia; // el de su ficha, siempre al día
        }
        $destino = $datos ? new Destino($datos['pais'], $datos['codigo_postal']) : null;
        // Los cupones que ya usó: el total que ve es el que se cobrará al confirmar (D169).
        $usados = $datos ? CuponesUsados::de($cliente, $cliente?->correo ?? $datos['correo'] ?? null) : [];
        $valoracion = $valorar($carrito, $destino, $datos ? (bool) $datos['recargo'] : false, $usados);
        $envio = $cliente?->direcciones()->where('tipo', TipoDireccion::Envio)->orderByDesc('predeterminada')->first();

        return view('carrito.cierre', [
            'cliente' => $cliente,
            'bloqueo' => $this->bloqueo($request, $cliente),
            'datos' => $datos,
            'valoracion' => $valoracion,
            'previa' => [
                'nombre' => $envio?->nombre ?? $cliente?->nombre, 'empresa' => $envio?->empresa, 'direccion' => $envio?->direccion,
                'direccion_2' => $envio?->direccion_2, 'codigo_postal' => $envio?->codigo_postal, 'localidad' => $envio?->localidad,
                'provincia' => $envio?->provincia, 'pais' => $envio?->pais ?? 'ES', 'telefono' => $envio?->telefono ?? $cliente?->telefono,
            ],
            'paises' => Paises::disponibles(),
            'recargoInvitado' => Ajuste::obtener('clientes.recargo_lo_indica') === 'cliente',
            'alternos' => collect(config('idiomas.disponibles'))->keys()->mapWithKeys(fn ($i) => [$i => CarritoController::url('cerrar', idioma: $i)])->all(),
        ]);
    }

    public function calcular(Request $request): RedirectResponse
    {
        abort_if(CarritoActual::de($request) === null, 404);
        $cliente = $this->cliente($request);
        if ($this->bloqueo($request, $cliente) !== null) {
            return redirect(CarritoController::url('cerrar'));
        }

        $request->merge([
            'pais' => strtoupper((string) $request->input('pais', 'ES')),
            'codigo_postal' => strtoupper(trim((string) $request->input('codigo_postal'))),
            'correo' => mb_strtolower(trim((string) $request->input('correo'))),
            'nif' => Nif::normalizar((string) $request->input('nif')),
        ]);
        $pais = (string) $request->input('pais');
        $reglas = [
            'nombre' => ['required', 'string', 'max:150'],
            'empresa' => ['nullable', 'string', 'max:150'],
            'direccion' => ['required', 'string', 'max:200'],
            'direccion_2' => ['nullable', 'string', 'max:200'],
            'codigo_postal' => ['required', 'string', 'max:10', function ($a, $v, $fallo) use ($pais) {
                if ($error = ReglasDireccion::errorCodigoPostal($pais, (string) $v, TipoDireccion::Envio->value)) {
                    $fallo($error);
                }
            }],
            'localidad' => ['required', 'string', 'max:100'],
            'provincia' => ['nullable', 'string', 'max:100'],
            'pais' => ['required', Rule::in(array_keys(Paises::disponibles()))],
            'telefono' => ['required', 'string', 'max:30', 'regex:'.ReglasDireccion::TELEFONO],
        ];
        if ($cliente === null) {
            $reglas += [
                'correo' => ['required', 'string', 'email', 'max:255'],
                'tipo' => ['required', Rule::enum(TipoCliente::class)],
                'nif' => ['nullable', 'required_unless:tipo,particular', 'string', 'max:20', fn ($a, $v, $fallo) => $v !== '' && ! Nif::valido($v) ? $fallo(__('cuenta.nif_no_valido')) : null],
                'recargo' => ['nullable', 'boolean'],
            ];
        }
        $datos = $request->validate($reglas, [], collect(array_keys($reglas))
            ->mapWithKeys(fn ($c) => [$c => mb_strtolower(__('cuenta.'.match ($c) {
                'nombre' => 'campo_nombre', 'recargo' => 'recargo_casilla', default => $c
            }))])->all());

        // El recargo: el de su ficha; un invitado solo lo marca si el backend deja que lo indique el cliente.
        $datos['recargo'] = $cliente !== null
            ? (bool) $cliente->recargo_equivalencia
            : (Ajuste::obtener('clientes.recargo_lo_indica') === 'cliente' && TipoCliente::from($datos['tipo'])->admiteRecargo() && $request->boolean('recargo'));
        $datos['cliente_id'] = $cliente?->id;
        $request->session()->put(self::SESION, $datos);

        return redirect(CarritoController::url('cerrar').'#total');
    }

    private function bloqueo(Request $request, ?Cliente $cliente): ?string
    {
        return self::bloqueoDe($request, $cliente);
    }

    /** Lo que impide cerrar con cuenta: correo sin verificar, ficha desactivada o datos fiscales incompletos. También al confirmar (D169). */
    public static function bloqueoDe(Request $request, ?Cliente $cliente): ?string
    {
        $usuario = $request->user();

        return match (true) {
            $usuario === null || $cliente === null => null,
            ! $usuario->hasVerifiedEmail() => 'correo',
            ! $cliente->activo => 'inactiva',
            $cliente->tipo !== TipoCliente::Particular && ($cliente->nif === null || ! Nif::valido($cliente->nif)) => 'fiscal',
            default => null,
        };
    }

    private function cliente(Request $request): ?Cliente
    {
        return $request->user()?->cliente;
    }
}
