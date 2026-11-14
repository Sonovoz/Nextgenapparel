@php
    use App\Dominio\Precios\Formato;
    $fuentes = ['Jost Bold', 'Lora Italic', 'Anton', 'Dancing Script'];
    $prendas = array_sum(array_column($filas, 'unidades'));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Manifiesto de pedido {{ $pedido->numero }}</title>
<style>
@include('pdf._estilos')
    h2 { font-family: 'Jost Medium'; font-size: 10.5pt; color: #0B0B10; margin: 7mm 0 2.5mm; }
    .manifiesto th, .manifiesto td { text-align: center; }
    .manifiesto th.izq, .manifiesto td.izq { text-align: left; }
    .manifiesto td.total, .manifiesto th.total { font-weight: bold; color: #0B0B10; }
    .manifiesto tr.suma td { background: #0B0B10; color: #FFFFFF; font-weight: bold; border: 0; }
    .muestra { display: inline-block; width: 3mm; height: 3mm; border-radius: 1.5mm; border: 0.25mm solid #C8C8D0; margin-right: 1.5mm; }
    .archivo { font-family: 'DejaVu Sans Mono', monospace; font-size: 7.4pt; color: #0B0B10; }
    .maquetas img { width: 40mm; margin: 0 3mm 2mm 0; border: 0.3mm solid #E6E6EC; }
    .bloque p { margin-bottom: 0.8mm; }
</style>
</head>
<body>
@include('pdf._pie', ['texto' => 'Manifiesto de pedido · '.$pedido->numero.' · contiene datos personales: uso interno'])

@include('pdf._cabecera', ['tipo' => 'Manifiesto de pedido', 'numero' => $pedido->numero, 'datos' => [
    'Pedido del '.$pedido->created_at->timezone('Europe/Madrid')->format('d/m/Y H:i').' · '.$prendas.' prendas',
    'Estado: '.e($pedido->estado->etiqueta()).' · Total '.e(Formato::euros($pedido->total, 'es')),
    '<strong style="color: #FFFFFF;">Entrega estimada: '.e($pedido->fecha_estimada_entrega?->format('d/m/Y') ?? '—').'</strong>',
]])

<table class="bloques" style="margin-top: 6mm;"><tr>
    <td style="width: 49%;"><div class="bloque">
        <p class="etiqueta">Cliente</p>
        <p class="fuerte">{{ $pedido->nombre_fiscal }}</p>
        @if ($pedido->nif)<p>NIF: {{ $pedido->nif }}@if ($pedido->recargo_equivalencia) · recargo de equivalencia @endif</p>@endif
        <p>{{ $pedido->correo }}</p>
        @if ($pedido->direccion_envio['telefono'] ?? null)<p>Tel. {{ $pedido->direccion_envio['telefono'] }}</p>@endif
    </div></td>
    <td style="width: 2%;"></td>
    <td style="width: 49%;"><div class="bloque">
        <p class="etiqueta">Envío</p>
        <p>{{ $direccion }}</p>
        @if ($pedido->transportista)<p>{{ $pedido->transportista }}@if ($pedido->numero_seguimiento) · {{ $pedido->numero_seguimiento }}@endif</p>@endif
    </div></td>
</tr></table>

<h2>Manifiesto de prendas</h2>
<table class="lista manifiesto">
    <thead><tr>
        <th class="izq">L</th><th class="izq">Prenda</th><th class="izq">Color</th>
        @foreach ($tallas as $talla => $total)<th>{{ $talla }}</th>@endforeach
        <th class="total">Total</th>
    </tr></thead>
    <tbody>
        @foreach ($filas as $fila)
            <tr>
                <td class="izq">L{{ $fila['linea'] }}</td>
                <td class="izq"><span class="fuerte">{{ $fila['prenda'] }}</span>
                    @if ($fila['referencia'])<br><span class="tenue pequena">Ref. {{ $fila['referencia'] }}</span>@endif
                    @if ($fila['pack'])<br><span class="tenue pequena">Pack: {{ $fila['pack'] }}</span>@endif
                    @if ($fila['coleccion'])<br><span class="tenue pequena">Colección: {{ $fila['coleccion'] }}</span>@endif
                </td>
                <td class="izq">@if ($fila['hex'])<span class="muestra" style="background: {{ $fila['hex'] }};"></span>@endif{{ $fila['color'] }}</td>
                @foreach ($tallas as $talla => $total)<td>{{ $fila['tallas'][$talla] ?? '' }}</td>@endforeach
                <td class="total">{{ $fila['unidades'] }}</td>
            </tr>
        @endforeach
        <tr class="suma">
            <td class="izq" colspan="3">Total por talla</td>
            @foreach ($tallas as $talla => $total)<td>{{ $total }}</td>@endforeach
            <td>{{ $prendas }}</td>
        </tr>
    </tbody>
</table>

<h2>Referencias de impresión</h2>
<table class="lista">
    <thead><tr><th>L</th><th>Zona</th><th>Diseño (carpeta «disenos/» del ZIP)</th><th>Medida</th><th>Posición</th><th>Calidad</th></tr></thead>
    <tbody>
        @forelse ($impresiones as $imp)
            <tr>
                <td class="corto">L{{ $imp['linea'] }}</td>
                <td class="corto"><span class="fuerte">{{ $imp['zona'] }}</span>@if ($imp['cara'])<br><span class="tenue pequena">{{ $imp['cara'] }}</span>@endif</td>
                <td>
                    @if ($imp['texto'] !== null)
                        Texto «{{ $imp['texto'] }}»
                    @elseif ($imp['borrado'])
                        <span class="tenue">Archivo borrado (custodia de 90 días hábiles)</span>
                    @else
                        <span class="archivo">{{ $imp['archivo'] }}</span>@if ($imp['original'])<br><span class="tenue pequena">subido como «{{ $imp['original'] }}»</span>@endif
                    @endif
                </td>
                <td class="corto fuerte">{{ $imp['medida'] }}</td>
                <td>{{ $imp['posicion'] }}</td>
                <td class="corto">{{ $imp['calidad'] ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="tenue">Sin impresiones.</td></tr>
        @endforelse
    </tbody>
</table>
<p class="tenue pequena" style="margin-top: 2mm;">Los diseños van tal como el cliente cerró el pedido, sin modificar. Imprímelos a la medida indicada, en cm.</p>

@if (collect($maquetas)->flatten()->isNotEmpty())
    <h2>Maquetas</h2>
    @foreach ($maquetas as $linea => $imagenes)
        @if ($imagenes)
            <div class="maquetas" style="page-break-inside: avoid;"><p class="tenue pequena">L{{ $linea }}</p>@foreach ($imagenes as $img)<img src="{{ $img }}" alt="">@endforeach</div>
        @endif
    @endforeach
@endif
</body>
</html>
