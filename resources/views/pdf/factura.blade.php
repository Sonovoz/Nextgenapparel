@php
    use App\Dominio\Documentos\EnlacesPdf;
    use App\Dominio\Precios\Formato;
    $i = $factura->idioma;
    $e = fn (int $c) => Formato::euros($c, $i);
    $pct = fn (int $centesimas) => rtrim(rtrim(number_format($centesimas / 100, 2, $i === 'en' ? '.' : ',', ''), '0'), ',.').' %';
    $emisor = $factura->emisor;
    $dest = $factura->destinatario;
    $dir = $dest['direccion'] ?? [];
    $correo = EnlacesPdf::correo($emisor['correo'] ?? null);
    $rectificativa = $factura->rectifica !== null;
@endphp
<!DOCTYPE html>
<html lang="{{ $i }}">
<head>
<meta charset="utf-8">
<title>{{ $factura->tipo->etiqueta($i) }} {{ $factura->codigo }}</title>
<style>
@include('pdf._estilos')
</style>
</head>
<body>
@include('pdf._pie', ['idioma' => $i, 'texto' => $emisor['razon_social'].' · '.__('facturas.nif', [], $i).' '.$emisor['nif'].' · '.$factura->codigo, 'enlaces' => EnlacesPdf::para($i)])

{{-- Veri*Factu: el QR va al principio de la factura (D175). --}}
@if ($factura->verifactu)
    <table class="bloques" style="margin-bottom: 5mm;"><tr>
        <td style="width: 34mm;"><img src="{{ $qr }}" alt="QR" style="width: 30mm; height: 30mm;"></td>
        <td style="vertical-align: middle;"><p style="margin: 0; font-size: 10pt; color: #0B0B10;"><strong>VERI*FACTU</strong></p><p class="pequena tenue" style="margin: 1mm 0 0;">{{ __('facturas.verifactu', [], $i) }}</p></td>
    </tr></table>
@endif

@php
    $datos = [__('facturas.fecha', [], $i).' '.$factura->emitida_en->timezone('Europe/Madrid')->format('d/m/Y'), e(__('facturas.pedido', [], $i).' '.$factura->pedido->numero)];
    if ($factura->rectifica) { $datos[] = e(__('facturas.rectifica', [], $i)).' <strong>'.e($factura->rectifica->codigo).'</strong>'; }
    if ($factura->motivo) { $datos[] = e(__('facturas.motivo', [], $i).': '.$factura->motivo); }
@endphp
@include('pdf._cabecera', ['tipo' => $factura->tipo->etiqueta($i), 'numero' => $factura->codigo, 'datos' => $datos])

{{-- D215: la emoción de haber encargado su colección (en las rectificativas, un texto neutro). --}}
<div class="bienvenida">
    @if ($rectificativa)
        <p class="entradilla" style="margin-top: 0;">{{ __('pdf.rectificativa_texto', [], $i) }}</p>
    @else
        <p class="titular">{{ __('pdf.factura_titular', [], $i) }}</p>
        <p class="entradilla">{{ __('pdf.factura_texto', [], $i) }}</p>
    @endif
</div>

@if (! empty($coleccion))
    <p class="seccion">{{ __('pdf.tu_coleccion', [], $i) }}</p>
    @include('pdf._coleccion', ['idioma' => $i, 'lineas' => $coleccion])
@endif

<table class="bloques" style="margin-top: 7mm;"><tr>
    <td style="width: 49%;"><div class="bloque">
        <p class="etiqueta">{{ __('facturas.emisor', [], $i) }}</p>
        <p><strong>{{ $emisor['razon_social'] }}</strong><br>{{ __('facturas.nif', [], $i) }} {{ $emisor['nif'] }}<br>{{ $emisor['direccion'] }}<br>
            {{ $emisor['codigo_postal'] }} {{ $emisor['localidad'] }}@if ($emisor['provincia']) ({{ $emisor['provincia'] }})@endif @if ($emisor['correo'])<br>{{ $emisor['correo'] }}@endif</p>
    </div></td>
    <td style="width: 2%;"></td>
    <td style="width: 49%;"><div class="bloque">
        <p class="etiqueta">{{ __('facturas.cliente', [], $i) }}</p>
        <p><strong>{{ $dest['nombre'] }}</strong>@if ($dest['nif'])<br>{{ __('facturas.nif', [], $i) }} {{ $dest['nif'] }}@endif
            @if ($factura->tipo->value !== 'F2' && $factura->tipo->value !== 'R5')<br>{{ $dir['direccion'] ?? '' }}@if (! empty($dir['direccion_2'])), {{ $dir['direccion_2'] }}@endif<br>{{ $dir['codigo_postal'] ?? '' }} {{ $dir['localidad'] ?? '' }}@if (! empty($dir['provincia'])) ({{ $dir['provincia'] }})@endif · {{ $dir['pais'] ?? '' }}@endif</p>
    </div></td>
</tr></table>

<table class="lista" style="margin-top: 7mm;">
    <thead><tr><th>{{ __('facturas.concepto', [], $i) }}</th><th class="num">{{ __('facturas.ud', [], $i) }}</th><th class="num">{{ __('facturas.precio', [], $i) }}</th><th class="num">{{ __('facturas.iva', [], $i) }}</th><th class="num">{{ __('facturas.importe', [], $i) }}</th></tr></thead>
    <tbody>
        @foreach ($factura->lineas as $l)
            <tr><td>{{ $l->descripcion }}</td><td class="num">{{ $l->cantidad }}</td><td class="num">{{ $e($l->precio_unitario) }}</td>
                <td class="num">{{ $l->tipo_iva === null ? __('facturas.segun_desglose', [], $i) : $pct($l->tipo_iva) }}</td><td class="num">{{ $e($l->base) }}</td></tr>
        @endforeach
    </tbody>
</table>

<table class="bloques" style="margin-top: 6mm;"><tr>
    <td style="width: 42%;"></td>
    <td style="width: 58%;">
        <table class="lista desglose">
            <thead><tr><th>{{ __('facturas.tipo_iva', [], $i) }}</th><th class="num">{{ __('facturas.base', [], $i) }}</th><th class="num">{{ __('facturas.cuota', [], $i) }}</th>@if ($factura->recargo !== 0)<th class="num">{{ __('facturas.recargo', [], $i) }}</th>@endif</tr></thead>
            <tbody>@foreach ($factura->desglose as $d)
                <tr><td class="corto">{{ $pct($d['tipo_iva']) }}</td><td class="num">{{ $e($d['base']) }}</td><td class="num">{{ $e($d['cuota']) }}</td>@if ($factura->recargo !== 0)<td class="num">{{ $pct($d['recargo']) }} · {{ $e($d['cuota_recargo']) }}</td>@endif</tr>
            @endforeach</tbody>
        </table>
        <table class="totales" style="margin-top: 3mm;"><tbody>
            <tr><td>{{ __('facturas.base', [], $i) }}</td><td class="num">{{ $e($factura->base_imponible) }}</td></tr>
            <tr><td>{{ __('facturas.iva', [], $i) }}</td><td class="num">{{ $e($factura->iva) }}</td></tr>
            @if ($factura->recargo !== 0)<tr><td>{{ __('facturas.recargo', [], $i) }}</td><td class="num">{{ $e($factura->recargo) }}</td></tr>@endif
            <tr class="total"><td>{{ __('facturas.total', [], $i) }}</td><td class="num">{{ $e($factura->total) }}</td></tr>
        </tbody></table>
    </td>
</tr></table>

<div class="disposicion"><table class="bloques"><tr>
    <td style="width: 62%;">
        <p class="titulo">{{ __('pdf.disposicion', [], $i) }}</p>
        <p class="tenue" style="margin-top: 1.2mm;">{{ __('pdf.disposicion_texto', [], $i) }}</p>
    </td>
    <td style="width: 38%; text-align: right; vertical-align: middle;">
        @if ($correo)<p><a href="mailto:{{ $correo }}" class="fuerte">{{ $correo }}</a></p>@endif
    </td>
</tr></table></div>
</body>
</html>
