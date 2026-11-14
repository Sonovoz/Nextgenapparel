@php
    use App\Dominio\Precios\Formato;
    $pedido = $getRecord();
    $pedido->loadMissing('lineas.impresiones', 'lineas.maquetas');
    $cm = fn (int $mm) => rtrim(rtrim(number_format($mm / 10, 1, ',', ''), '0'), ',');
@endphp
<div style="display: flex; flex-direction: column; gap: 20px;">
    @foreach ($pedido->lineas as $linea)
        <div style="display: flex; gap: 16px; flex-wrap: wrap; padding-bottom: 16px; border-bottom: 1px solid rgba(127,127,127,.2);">
            <div style="display: flex; gap: 8px;">
                @forelse ($linea->maquetas as $maqueta)
                    <a href="{{ route('backend.pedidos.maqueta', ['pedido' => $pedido->uuid, 'maqueta' => $maqueta->id]) }}" target="_blank">
                        <img src="{{ route('backend.pedidos.maqueta', ['pedido' => $pedido->uuid, 'maqueta' => $maqueta->id]) }}" alt="Maqueta {{ $maqueta->cara }}" style="width: 110px; border-radius: 8px; background: #f3f3f3;">
                    </a>
                @empty
                    <div style="width: 110px; height: 132px; border-radius: 8px; background: rgba(127,127,127,.12); display: flex; align-items: center; justify-content: center; font-size: 12px; text-align: center; padding: 8px;">Maqueta pendiente</div>
                @endforelse
            </div>
            <div style="flex: 1; min-width: 220px; font-size: 14px; display: flex; flex-direction: column; gap: 6px;">
                <strong>{{ $linea->nombreEn('es') }}@if ($linea->colorEn('es')) · {{ $linea->colorEn('es') }}@endif @if ($linea->tipo === 'pack') (pack)@endif</strong>
                @if ($linea->coleccion)<span>Colección: <strong>{{ $linea->coleccion }}</strong></span>@endif
                <span>
                    @if ($linea->tallas){{ collect($linea->tallas)->map(fn ($t) => $t['nombre'].' × '.$t['unidades'])->join(' · ') }} · @endif
                    {{ $linea->unidades }} {{ $linea->unidades === 1 ? 'prenda' : 'prendas' }}
                </span>
                @foreach ($linea->impresiones as $impresion)
                    <span>
                        <strong>{{ $impresion->zonaEn('es') }}</strong>:
                        @if ($impresion->texto !== null)
                            texto «{{ $impresion->texto }}» (letra {{ $impresion->fuente + 1 }}, tinta {{ $impresion->tinta }})
                        @else
                            {{ $impresion->nombre_original }}
                            @if ($impresion->archivo)
                                · <a href="{{ route('backend.pedidos.original', ['pedido' => $pedido->uuid, 'impresion' => $impresion->id]) }}" style="text-decoration: underline;">descargar original</a>
                                · <a href="{{ route('backend.pedidos.tamano-real', ['pedido' => $pedido->uuid, 'impresion' => $impresion->id]) }}" style="text-decoration: underline;">descargar a tamaño real ({{ \App\Dominio\Produccion\ArchivoTamanoReal::medida($impresion) }})</a>
                            @elseif ($pedido->archivos_borrados_en)
                                · <span style="opacity: .7;">original borrado el {{ $pedido->archivos_borrados_en->format('d/m/Y') }} (D230)</span>
                            @endif
                        @endif
                        · {{ $cm($impresion->ancho_mm) }} × {{ $cm($impresion->alto_mm) }} cm, a {{ $cm($impresion->desde_cuello_mm) }} cm del cuello
                        @if ($impresion->ppp) · {{ $impresion->ppp }} ppp ({{ $impresion->nivel_calidad }}) @endif
                    </span>
                @endforeach
                {{-- D241: referencias de impresión de cada zona, en tabla. --}}
                @if ($linea->impresiones->isNotEmpty())
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 4px;">
                        <thead><tr style="text-align: left; font-size: 11px; letter-spacing: .06em; text-transform: uppercase; opacity: .6;">
                            <th style="padding: 6px 8px 6px 0;">Zona</th><th style="padding: 6px 8px;">Diseño</th><th style="padding: 6px 8px;">Medida</th><th style="padding: 6px 8px;">Posición</th><th style="padding: 6px 8px;">Calidad</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($linea->impresiones as $impresion)
                                <tr style="border-top: 1px solid rgba(127,127,127,.18);">
                                    <td style="padding: 8px 8px 8px 0;"><strong>{{ $impresion->zonaEn('es') }}</strong></td>
                                    <td style="padding: 8px;">
                                        @if ($impresion->texto !== null)
                                            Texto «{{ $impresion->texto }}» (letra {{ $impresion->fuente + 1 }}, tinta {{ $impresion->tinta }})
                                        @else
                                            {{ $impresion->nombre_original }}
                                            @if ($impresion->archivo)
                                                · <a href="{{ route('backend.pedidos.original', ['pedido' => $pedido->uuid, 'impresion' => $impresion->id]) }}" style="text-decoration: underline;">descargar original</a>
                                            @elseif ($pedido->archivos_borrados_en)
                                                · <span style="opacity: .7;">original borrado el {{ $pedido->archivos_borrados_en->format('d/m/Y') }} (D230)</span>
                                            @endif
                                        @endif
                                    </td>
                                    <td style="padding: 8px; white-space: nowrap;"><strong>{{ $cm($impresion->ancho_mm) }} × {{ $cm($impresion->alto_mm) }} cm</strong></td>
                                    <td style="padding: 8px;">{{ $cm($impresion->desde_cuello_mm) }} cm bajo el cuello · {{ $impresion->desde_centro_mm === 0 ? 'centrado' : $cm(abs($impresion->desde_centro_mm)).' cm a la '.($impresion->desde_centro_mm < 0 ? 'izquierda' : 'derecha') }}</td>
                                    <td style="padding: 8px; white-space: nowrap;">@if ($impresion->ppp){{ $impresion->ppp }} ppp ({{ $impresion->nivel_calidad }})@else — @endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
                <span>{{ Formato::euros($linea->neto, 'es') }} sin IVA @if ($linea->descuento + $linea->cupon > 0)(descuentos −{{ Formato::euros($linea->descuento + $linea->cupon, 'es') }})@endif</span>
            </div>
        </div>
    @endforeach
</div>
