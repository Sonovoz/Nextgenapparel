{{-- D241: manifiesto de prendas del pedido: prenda × color × talla, con los totales. --}}
@php
    use App\Dominio\Pedidos\ManifiestoPedido;
    $pedido = $getRecord();
    $filas = ManifiestoPedido::prendas($pedido);
    $tallas = ManifiestoPedido::tallas($filas);
    $total = array_sum(array_column($filas, 'unidades'));
@endphp
<style>
    .manifiesto-tabla { width: 100%; border-collapse: collapse; font-size: 14px; }
    .manifiesto-tabla th { font-size: 11px; letter-spacing: .08em; text-transform: uppercase; opacity: .65; font-weight: 600; padding: 8px 10px; border-bottom: 1px solid rgba(127,127,127,.35); text-align: center; }
    .manifiesto-tabla td { padding: 10px; border-bottom: 1px solid rgba(127,127,127,.15); text-align: center; vertical-align: middle; }
    .manifiesto-tabla .izq { text-align: left; }
    .manifiesto-tabla td.talla { font-size: 16px; font-weight: 600; }
    .manifiesto-tabla td.vacia { opacity: .25; }
    .manifiesto-tabla .total { font-weight: 700; font-size: 16px; }
    .manifiesto-tabla tfoot td { font-weight: 700; border-bottom: 0; background: rgba(127,127,127,.1); }
    .manifiesto-muestra { display: inline-block; width: 14px; height: 14px; border-radius: 99px; border: 1px solid rgba(127,127,127,.5); vertical-align: -2px; margin-right: 6px; }
    .manifiesto-detalle { display: block; font-size: 12px; opacity: .65; }
    .manifiesto-scroll { overflow-x: auto; }
</style>
@if ($filas === [])
    <p style="opacity: .7;">Sin prendas.</p>
@else
    <div class="manifiesto-scroll">
        <table class="manifiesto-tabla">
            <thead><tr>
                <th class="izq">Línea</th><th class="izq">Prenda</th><th class="izq">Color</th>
                @foreach ($tallas as $talla => $n)<th>{{ $talla }}</th>@endforeach
                <th>Total</th>
            </tr></thead>
            <tbody>
                @foreach ($filas as $fila)
                    <tr>
                        <td class="izq">L{{ $fila['linea'] }}</td>
                        <td class="izq"><strong>{{ $fila['prenda'] }}</strong>
                            @if ($fila['referencia'])<span class="manifiesto-detalle">Ref. {{ $fila['referencia'] }}</span>@endif
                            @if ($fila['pack'])<span class="manifiesto-detalle">Pack: {{ $fila['pack'] }}</span>@endif
                            @if ($fila['coleccion'])<span class="manifiesto-detalle">Colección: {{ $fila['coleccion'] }}</span>@endif
                        </td>
                        <td class="izq">@if ($fila['hex'])<span class="manifiesto-muestra" style="background: {{ $fila['hex'] }};"></span>@endif{{ $fila['color'] }}</td>
                        @foreach ($tallas as $talla => $n)
                            <td class="{{ isset($fila['tallas'][$talla]) ? 'talla' : 'vacia' }}">{{ $fila['tallas'][$talla] ?? '·' }}</td>
                        @endforeach
                        <td class="total">{{ $fila['unidades'] }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot><tr>
                <td class="izq" colspan="3">Total por talla</td>
                @foreach ($tallas as $talla => $n)<td>{{ $n }}</td>@endforeach
                <td class="total">{{ $total }}</td>
            </tr></tfoot>
        </table>
    </div>
@endif
