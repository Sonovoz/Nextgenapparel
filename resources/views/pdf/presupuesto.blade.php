@php
    use App\Dominio\Precios\Formato;
    $i = $presupuesto->idioma;
    $e = fn (int $c) => Formato::euros($c, $i);
    $c = $presupuesto->contenido;
@endphp
<!DOCTYPE html>
<html lang="{{ $i }}">
<head>
<meta charset="utf-8">
<title>{{ __('presupuestos.titulo', ['numero' => $presupuesto->numero], $i) }}</title>
<style>
@include('pdf._estilos')
    .maquetas img { width: 40mm; margin: 2.5mm 2mm 0 0; border: 0.3mm solid #E6E6EC; }
</style>
</head>
<body>
@include('pdf._pie', ['texto' => ($emisor['razon_social'] ?? 'Nextgenapparel').($emisor['nif'] ? ' · '.$emisor['nif'] : '').' · '.$presupuesto->numero])

@include('pdf._cabecera', ['tipo' => __('presupuestos.tipo_documento', [], $i), 'numero' => $presupuesto->numero, 'datos' => [
    e($presupuesto->created_at->timezone('Europe/Madrid')->format('d/m/Y')),
    e(__('presupuestos.valido', ['fecha' => $presupuesto->valido_hasta->locale($i)->isoFormat('LL')], $i)),
]])

<div class="bloque" style="width: 60%;">
    <p class="etiqueta">{{ __('facturas.emisor', [], $i) }}</p>
    <p><strong>{{ $emisor['razon_social'] ?? 'Nextgenapparel' }}</strong>@if ($emisor['nif']) · {{ $emisor['nif'] }}@endif<br><span class="tenue">{{ $emisor['direccion'] }} {{ $emisor['codigo_postal'] }} {{ $emisor['localidad'] }}</span></p>
</div>

<table class="lista" style="margin-top: 7mm;">
    <thead><tr><th>{{ __('presupuestos.concepto', [], $i) }}</th><th class="num">{{ __('presupuestos.unidades', [], $i) }}</th><th class="num">{{ __('presupuestos.importe', [], $i) }}</th></tr></thead>
    <tbody>
        @foreach ($c['lineas'] as $l)
            <tr><td><strong>{{ $l['descripcion'] }}</strong><br><span class="tenue">{{ $l['detalle'] }}</span>
                {{-- Lo que ha creado: la maqueta de cada cara con sus diseños (D200). --}}
                @if (! empty($maquetas[$loop->index]))<div class="maquetas">@foreach ($maquetas[$loop->index] as $img)<img src="{{ $img }}" alt="">@endforeach</div>@endif</td>
                <td class="num">{{ $l['unidades'] }}</td><td class="num">{{ $e($l['neto']) }}</td></tr>
        @endforeach
    </tbody>
</table>

<table class="bloques" style="margin-top: 6mm;"><tr>
    <td style="width: 50%;"></td>
    <td style="width: 50%;">
        <table class="totales"><tbody>
            <tr><td>{{ __('carrito.prendas_impresion', [], $i) }}</td><td class="num">{{ $e($c['bruto']) }}</td></tr>
            @if ($c['descuentos'] > 0)<tr><td>{{ $c['descuento_nombre'] ?? __('carrito.descuentos', [], $i) }}</td><td class="num">−{{ $e($c['descuentos']) }}</td></tr>@endif
            <tr><td>{{ __('carrito.base', [], $i) }}</td><td class="num">{{ $e($c['base']) }}</td></tr>
            <tr><td>{{ __('carrito.iva', [], $i) }}</td><td class="num">{{ $e($c['impuestos']) }}</td></tr>
            <tr class="total"><td>{{ __('carrito.total', [], $i) }}</td><td class="num">{{ $e($c['total']) }}</td></tr>
        </tbody></table>
    </td>
</tr></table>

<div class="nota">
    <p style="margin: 0;">{{ __('presupuestos.sin_envio', [], $i) }}</p>
    <p class="pequena tenue" style="margin: 1.5mm 0 0;">{{ __('presupuestos.enlace_pdf', [], $i) }} {{ $enlace }}</p>
</div>
</body>
</html>
