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
    @page { margin: 22mm 16mm 20mm 16mm; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; color: #111; line-height: 1.4; }
    h1 { font-size: 16pt; margin: 0 0 2mm; }
    .tenue { color: #666; }
    table { width: 100%; border-collapse: collapse; margin-top: 6mm; }
    th { text-align: left; font-size: 7.5pt; text-transform: uppercase; color: #555; border-bottom: 1.5px solid #111; padding: 1.5mm; }
    td { border-bottom: 1px solid #e5e5e5; padding: 1.8mm 1.5mm; vertical-align: top; }
    .num { text-align: right; white-space: nowrap; }
    .totales td { border: 0; padding: 1mm 1.5mm; }
    .maquetas img { width: 42mm; margin: 2.5mm 2mm 0 0; border: 1px solid #e5e5e5; }
</style>
</head>
<body>
    <p><strong>{{ $emisor['razon_social'] ?? 'Nextgenapparel' }}</strong>@if ($emisor['nif']) · {{ $emisor['nif'] }}@endif<br><span class="tenue">{{ $emisor['direccion'] }} {{ $emisor['codigo_postal'] }} {{ $emisor['localidad'] }}</span></p>
    <h1>{{ __('presupuestos.titulo', ['numero' => $presupuesto->numero], $i) }}</h1>
    <p class="tenue">{{ $presupuesto->created_at->timezone('Europe/Madrid')->format('d/m/Y') }} · {{ __('presupuestos.valido', ['fecha' => $presupuesto->valido_hasta->locale($i)->isoFormat('LL')], $i) }}</p>
    <table>
        <thead><tr><th>{{ __('presupuestos.concepto', [], $i) }}</th><th class="num">{{ __('presupuestos.unidades', [], $i) }}</th><th class="num">{{ __('presupuestos.importe', [], $i) }}</th></tr></thead>
        <tbody>
            @foreach ($c['lineas'] as $l)
                <tr><td><strong>{{ $l['descripcion'] }}</strong><br><span class="tenue">{{ $l['detalle'] }}</span>
                    {{-- Lo que ha creado: la maqueta de cada cara con sus diseños (D200). --}}
                    @if (! empty($maquetas[$loop->index]))<div class="maquetas">@foreach ($maquetas[$loop->index] as $img)<img src="{{ $img }}" alt="">@endforeach</div>@endif</td><td class="num">{{ $l['unidades'] }}</td><td class="num">{{ $e($l['neto']) }}</td></tr>
            @endforeach
        </tbody>
    </table>
    <table class="totales">
        <tr><td class="num">{{ __('carrito.prendas_impresion', [], $i) }}</td><td class="num" style="width: 30mm;">{{ $e($c['bruto']) }}</td></tr>
        @if ($c['descuentos'] > 0)<tr><td class="num">{{ $c['descuento_nombre'] ?? __('carrito.descuentos', [], $i) }}</td><td class="num">−{{ $e($c['descuentos']) }}</td></tr>@endif
        <tr><td class="num">{{ __('carrito.base', [], $i) }}</td><td class="num">{{ $e($c['base']) }}</td></tr>
        <tr><td class="num">{{ __('carrito.iva', [], $i) }}</td><td class="num">{{ $e($c['impuestos']) }}</td></tr>
        <tr><td class="num"><strong>{{ __('carrito.total', [], $i) }}</strong></td><td class="num"><strong>{{ $e($c['total']) }}</strong></td></tr>
    </table>
    <p class="tenue">{{ __('presupuestos.sin_envio', [], $i) }}</p>
    <p>{{ __('presupuestos.enlace_pdf', [], $i) }}<br>{{ $enlace }}</p>
</body>
</html>
