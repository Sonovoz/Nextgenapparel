@php
    use App\Dominio\Documentos\EnlacesPdf;
    use App\Dominio\Precios\Formato;
    $i = $presupuesto->idioma;
    $e = fn (int $c) => Formato::euros($c, $i);
    $c = $presupuesto->contenido;
    $correo = EnlacesPdf::correo($emisor['correo'] ?? null);
@endphp
<!DOCTYPE html>
<html lang="{{ $i }}">
<head>
<meta charset="utf-8">
<title>{{ __('presupuestos.titulo', ['numero' => $presupuesto->numero], $i) }}</title>
<style>
@include('pdf._estilos')
    .pasos td { border: 0; padding: 0.8mm 0; vertical-align: top; }
    .paso-num { width: 7mm; font-family: 'Jost Medium'; color: #2D4BFF; }
    .boton { display: inline-block; background: #2D4BFF; color: #FFFFFF; border-radius: 6mm; padding: 2.4mm 6mm; font-family: 'Jost Medium'; font-size: 8.5pt; letter-spacing: 1.2px; text-transform: uppercase; margin-top: 3mm; }
</style>
</head>
<body>
@include('pdf._pie', ['idioma' => $i, 'texto' => ($emisor['razon_social'] ?? 'Nextgenapparel').(! empty($emisor['nif']) ? ' · '.$emisor['nif'] : '').' · '.$presupuesto->numero, 'enlaces' => EnlacesPdf::para($i)])

@include('pdf._cabecera', ['tipo' => __('presupuestos.tipo_documento', [], $i), 'numero' => $presupuesto->numero, 'datos' => [
    e(__('pdf.fecha_presupuesto', ['fecha' => $presupuesto->created_at->timezone('Europe/Madrid')->format('d/m/Y')], $i)),
]])

<div class="bienvenida">
    <p class="titular">{{ __('pdf.presupuesto_titular', [], $i) }}</p>
    <p class="entradilla">{{ __('pdf.presupuesto_texto', [], $i) }}</p>
</div>

<p class="seccion">{{ __('pdf.tu_coleccion', [], $i) }}</p>
{{-- Lo que ha creado: la maqueta de cada cara con sus diseños (D200), con su precio. --}}
@php
    $coleccion = [];
    foreach ($c['lineas'] as $n => $l) {
        $coleccion[] = ['nombre' => $l['descripcion'], 'detalle' => $l['detalle'], 'fotos' => $maquetas[$n] ?? [],
            'extra' => __('pdf.unidades', ['n' => $l['unidades']], $i).' · '.__('pedidos.sin_iva', [], $i), 'importe' => $e($l['neto'])];
    }
@endphp
@include('pdf._coleccion', ['idioma' => $i, 'lineas' => $coleccion])

<table class="bloques" style="margin-top: 6mm;"><tr>
    <td style="width: 46%;"></td>
    <td style="width: 54%;">
        <table class="totales"><tbody>
            <tr><td class="tenue">{{ __('carrito.prendas_impresion', [], $i) }}</td><td class="num">{{ $e($c['bruto']) }}</td></tr>
            @if ($c['descuentos'] > 0)<tr><td class="tenue">{{ $c['descuento_nombre'] ?? __('carrito.descuentos', [], $i) }}</td><td class="num">−{{ $e($c['descuentos']) }}</td></tr>@endif
            <tr><td class="tenue">{{ __('carrito.base', [], $i) }}</td><td class="num">{{ $e($c['base']) }}</td></tr>
            <tr><td class="tenue">{{ __('carrito.iva', [], $i) }}</td><td class="num">{{ $e($c['impuestos']) }}</td></tr>
            <tr class="total"><td>{{ __('carrito.total', [], $i) }}*</td><td class="num">{{ $e($c['total']) }}</td></tr>
        </tbody></table>
        <p class="pequena tenue" style="margin-top: 2mm; text-align: right;">{{ __('presupuestos.sin_envio', [], $i) }}</p>
        {{-- D216: sin fecha de vencimiento en la cabecera; la validez, con el aviso de precios. --}}
        <p class="pequena" style="margin-top: 1.5mm; text-align: right; color: #0B0B10;">* {{ __('pdf.aviso_precios', ['horas' => max(1, (int) round($presupuesto->created_at->diffInHours($presupuesto->valido_hasta)))], $i) }}</p>
    </td>
</tr></table>

<div class="nota">
    <p class="etiqueta" style="color: #2D4BFF;">{{ __('pdf.proximos', [], $i) }}</p>
    <table class="pasos"><tbody>
        @foreach (['paso_1', 'paso_2', 'paso_3'] as $n => $paso)
            <tr><td class="paso-num">0{{ $n + 1 }}</td><td>{{ __('pdf.'.$paso, [], $i) }}</td></tr>
        @endforeach
    </tbody></table>
    <a class="boton" href="{{ $enlace }}">{{ __('pdf.abrir_presupuesto', [], $i) }}</a>
    <p class="pequena tenue" style="margin-top: 2.5mm;">{{ $enlace }}</p>
</div>

<div class="disposicion"><table class="bloques"><tr>
    <td style="width: 62%;">
        <p class="titulo">{{ __('pdf.disposicion', [], $i) }}</p>
        <p class="tenue" style="margin-top: 1.2mm;">{{ __('pdf.disposicion_texto', [], $i) }}</p>
    </td>
    <td style="width: 38%; text-align: right; vertical-align: middle;">
        @if ($correo)<p><a href="mailto:{{ $correo }}" class="fuerte">{{ $correo }}</a></p>@endif
        <p class="pequena tenue" style="margin-top: 1mm;">{{ __('pdf.preparado_por', [], $i) }} {{ $emisor['razon_social'] ?? 'Nextgenapparel' }}</p>
    </td>
</tr></table></div>
</body>
</html>
