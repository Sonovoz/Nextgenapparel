<?php

namespace App\Http\Controllers\Cuenta;

use App\Dominio\Clientes\Direcciones;
use App\Dominio\Clientes\Paises;
use App\Dominio\Clientes\TipoDireccion;
use App\Models\Direccion;
use App\Models\ZonaEnvio;
use App\Support\RutaCuenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Direcciones del cliente (paso 5.3): varias de cada tipo, una predeterminada por tipo. */
class DireccionesController
{
    use ConCliente;

    public function lista(Request $request): View
    {
        $cliente = $this->cliente($request);

        return view('cuenta.direcciones', [
            'direcciones' => $cliente->direcciones()->orderBy('tipo')->orderByDesc('predeterminada')->latest('id')->get()->groupBy(fn ($d) => $d->tipo->value),
            'alternos' => RutaCuenta::alternos('direcciones'),
        ]);
    }

    public function crear(Request $request): View
    {
        $this->cliente($request);

        return $this->formulario(new Direccion(['tipo' => TipoDireccion::from($request->query('tipo', 'envio') === 'facturacion' ? 'facturacion' : 'envio'), 'pais' => 'ES']), RutaCuenta::alternos('direcciones.crear'));
    }

    public function guardar(Request $request, Direcciones $direcciones): RedirectResponse
    {
        $direcciones->guardar($this->cliente($request), $this->validar($request));

        return redirect(RutaCuenta::url('direcciones'))->with('estado', __('cuenta.direccion_guardada'));
    }

    public function editar(Request $request, string $direccion): View
    {
        $direccion = $this->suya($request, $direccion);

        return $this->formulario($direccion, RutaCuenta::alternos('direcciones.editar', ['direccion' => $direccion->uuid]));
    }

    public function actualizar(Request $request, string $direccion, Direcciones $direcciones): RedirectResponse
    {
        $modelo = $this->suya($request, $direccion);
        $direcciones->guardar($modelo->cliente, $this->validar($request), $modelo);

        return redirect(RutaCuenta::url('direcciones'))->with('estado', __('cuenta.direccion_guardada'));
    }

    public function predeterminar(Request $request, string $direccion, Direcciones $direcciones): RedirectResponse
    {
        $direcciones->predeterminar($this->suya($request, $direccion));

        return redirect(RutaCuenta::url('direcciones'))->with('estado', __('cuenta.direccion_guardada'));
    }

    public function borrar(Request $request, string $direccion, Direcciones $direcciones): RedirectResponse
    {
        $direcciones->borrar($this->suya($request, $direccion));

        return redirect(RutaCuenta::url('direcciones'))->with('estado', __('cuenta.direccion_borrada'));
    }

    /** Solo las del cliente que ha entrado; las de otro, 404. */
    private function suya(Request $request, string $uuid): Direccion
    {
        return $this->cliente($request)->direcciones()->where('uuid', $uuid)->firstOrFail();
    }

    private function formulario(Direccion $direccion, array $alternos): View
    {
        return view('cuenta.direccion', ['direccion' => $direccion, 'paises' => Paises::disponibles(), 'alternos' => $alternos]);
    }

    /** @return array<string, mixed> */
    private function validar(Request $request): array
    {
        $paises = array_keys(Paises::disponibles());
        $request->merge(['pais' => strtoupper((string) $request->input('pais', 'ES')), 'codigo_postal' => strtoupper(trim((string) $request->input('codigo_postal')))]);
        $pais = (string) $request->input('pais');

        return $request->validate([
            'tipo' => ['required', Rule::enum(TipoDireccion::class)],
            'nombre' => ['required', 'string', 'max:150'],
            'empresa' => ['nullable', 'string', 'max:150'],
            'direccion' => ['required', 'string', 'max:200'],
            'direccion_2' => ['nullable', 'string', 'max:200'],
            'codigo_postal' => ['required', 'string', 'max:10', function ($a, $v, $fallo) use ($pais, $request) {
                if (! preg_match(Paises::patronCodigoPostal($pais), $v)) {
                    return $fallo(__('cuenta.cp_no_valido', ['pais' => Paises::nombre($pais)]));
                }
                // Una dirección de envío tiene que caer en una zona de envío (misma regla que el motor, M4).
                if ($request->input('tipo') === TipoDireccion::Envio->value && ZonaEnvio::where('activa', true)->exists() && ZonaEnvio::paraDestino($pais, $v) === null) {
                    $fallo(__('cuenta.cp_sin_envio'));
                }
            }],
            'localidad' => ['required', 'string', 'max:100'],
            'provincia' => ['nullable', 'string', 'max:100'],
            'pais' => ['required', Rule::in($paises)],
            'telefono' => ['nullable', 'string', 'max:30', 'regex:/^[+0-9 ()\-]{6,30}$/'],
            'predeterminada' => ['nullable', 'boolean'],
        ], [], collect(['nombre', 'empresa', 'direccion', 'direccion_2', 'codigo_postal', 'localidad', 'provincia', 'pais', 'telefono'])
            ->mapWithKeys(fn ($c) => [$c => mb_strtolower(__('cuenta.'.($c === 'nombre' ? 'campo_nombre' : $c)))])->all());
    }
}
