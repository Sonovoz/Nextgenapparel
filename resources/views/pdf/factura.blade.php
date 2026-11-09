@php
    use App\Dominio\Precios\Formato;
    $i = $factura->idioma;
    $e = fn (int $c) => Formato::euros($c, $i);
    $pct = fn (int $centesimas) => rtrim(rtrim(number_format($centesimas / 100, 2, $i === 'en' ? '.' : ',', ''), '0'), ',.').' %';
    $emisor = $factura->emisor;
    $dest = $factura->destinatario;
    $dir = $dest['direccion'] ?? [];
@endphp
<!DOCTYPE html>
<html lang="{{ $i }}">
<head>
<meta charset="utf-8">
<title>{{ $factura->tipo->etiqueta($i) }} {{ $factura->codigo }}</title>
<style>
    @page { margin: 22mm 16mm 20mm 16mm; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; color: #111; line-height: 1.4; }
    footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 7.5pt; color: #777; border-top: 1px solid #ddd; padding-top: 2mm; }
    h1 { font-size: 16pt; margin: 0 0 2mm; }
    h2 { font-size: 10pt; margin: 6mm 0 2mm; padding-bottom: 1mm; border-bottom: 1px solid #ccc; }
    .marca { font-size: 13pt; font-weight: bold; letter-spacing: 1px; }
    .tenue { color: #666; }
    .pequena { font-size: 7.5pt; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; font-size: 7.5pt; text-transform: uppercase; color: #555; border-bottom: 1.5px solid #111; padding: 1.5mm; }
    td { border-bottom: 1px solid #e5e5e5; padding: 1.8mm 1.5mm; vertical-align: top; }
    .num { text-align: right; white-space: nowrap; }
    .rejilla td { border: 0; padding: 0 4mm 0 0; }
</style>
</head>
<body>
<footer>{{ $emisor['razon_social'] }} · {{ __('facturas.nif', [], $i) }} {{ $emisor['nif'] }} · {{ $factura->codigo }}</footer>

@if ($factura->verifactu)
    <table class="rejilla" style="margin-bottom: 4mm;"><tr>
        <td style="width: 34mm;"><img src="{{ $qr }}" alt="QR" style="width: 32mm; height: 32mm;"></td>
        <td style="vertical-align: middle;"><p style="margin: 0; font-size: 11pt;"><strong>VERI*FACTU</strong></p><p class="pequena tenue" style="margin: 1mm 0 0;">{{ __('facturas.verifactu', [], $i) }}</p></td>
    </tr></table>
@endif

<table class="rejilla"><tr>
    <td style="width: 55%;">
        <div class="marca">{{ $emisor['razon_social'] }}</div>
        <h1 style="margin-top: 4mm;">{{ $factura->tipo->etiqueta($i) }}</h1>
        <p>{{ __('facturas.numero', [], $i) }} <strong>{{ $factura->codigo }}</strong><br>
            {{ __('facturas.fecha', [], $i) }} {{ $factura->emitida_en->timezone('Europe/Madrid')->format('d/m/Y') }}<br>
            {{ __('facturas.pedido', [], $i) }} {{ $factura->pedido->numero }}
            @if ($factura->rectifica)<br>{{ __('facturas.rectifica', [], $i) }} <strong>{{ $factura->rectifica->codigo }}</strong>@endif
            @if ($factura->motivo)<br>{{ __('facturas.motivo', [], $i) }}: {{ $factura->motivo }}@endif</p>
    </td>
    <td style="width: 45%;">
        <p class="pequena tenue" style="margin: 0;">{{ mb_strtoupper(__('facturas.emisor', [], $i)) }}</p>
        <p style="margin: 1mm 0 0;"><strong>{{ $emisor['razon_social'] }}</strong><br>{{ __('facturas.nif', [], $i) }} {{ $emisor['nif'] }}<br>{{ $emisor['direccion'] }}<br>
            {{ $emisor['codigo_postal'] }} {{ $emisor['localidad'] }}@if ($emisor['provincia']) ({{ $emisor['provincia'] }})@endif @if ($emisor['correo'])<br>{{ $emisor['correo'] }}@endif</p>
        <p class="pequena tenue" style="margin: 3mm 0 0;">{{ mb_strtoupper(__('facturas.cliente', [], $i)) }}</p>
        <p style="margin: 1mm 0 0;"><strong>{{ $dest['nombre'] }}</strong>@if ($dest['nif'])<br>{{ __('facturas.nif', [], $i) }} {{ $dest['nif'] }}@endif
            @if ($factura->tipo->value !== 'F2' && $factura->tipo->value !== 'R5')<br>{{ $dir['direccion'] ?? '' }}@if (! empty($dir['direccion_2'])), {{ $dir['direccion_2'] }}@endif<br>{{ $dir['codigo_postal'] ?? '' }} {{ $dir['localidad'] ?? '' }}@if (! empty($dir['provincia'])) ({{ $dir['provincia'] }})@endif · {{ $dir['pais'] ?? '' }}@endif</p>
    </td>
</tr></table>

<h2>{{ __('facturas.concepto', [], $i) }}</h2>
<table>
    <thead><tr><th>{{ __('facturas.concepto', [], $i) }}</th><th class="num">{{ __('facturas.ud', [], $i) }}</th><th class="num">{{ __('facturas.precio', [], $i) }}</th><th class="num">{{ __('facturas.iva', [], $i) }}</th><th class="num">{{ __('facturas.importe', [], $i) }}</th></tr></thead>
    <tbody>
        @foreach ($factura->lineas as $l)
            <tr><td>{{ $l->descripcion }}</td><td class="num">{{ $l->cantidad }}</td><td class="num">{{ $e($l->precio_unitario) }}</td>
                <td class="num">{{ $l->tipo_iva === null ? __('facturas.segun_desglose', [], $i) : $pct($l->tipo_iva) }}</td><td class="num">{{ $e($l->base) }}</td></tr>
        @endforeach
    </tbody>
</table>

<table class="rejilla" style="margin-top: 5mm;"><tr>
    <td style="width: 45%;"></td>
    <td style="width: 55%;">
        <table>
            <thead><tr><th>{{ __('facturas.tipo_iva', [], $i) }}</th><th class="num">{{ __('facturas.base', [], $i) }}</th><th class="num">{{ __('facturas.cuota', [], $i) }}</th>@if ($factura->recargo !== 0)<th class="num">{{ __('facturas.recargo', [], $i) }}</th>@endif</tr></thead>
            <tbody>@foreach ($factura->desglose as $d)
                <tr><td>{{ $pct($d['tipo_iva']) }}</td><td class="num">{{ $e($d['base']) }}</td><td class="num">{{ $e($d['cuota']) }}</td>@if ($factura->recargo !== 0)<td class="num">{{ $pct($d['recargo']) }} · {{ $e($d['cuota_recargo']) }}</td>@endif</tr>
            @endforeach</tbody>
        </table>
        <table style="margin-top: 2mm;"><tbody>
            <tr><td>{{ __('facturas.base', [], $i) }}</td><td class="num">{{ $e($factura->base_imponible) }}</td></tr>
            <tr><td>{{ __('facturas.iva', [], $i) }}</td><td class="num">{{ $e($factura->iva) }}</td></tr>
            @if ($factura->recargo !== 0)<tr><td>{{ __('facturas.recargo', [], $i) }}</td><td class="num">{{ $e($factura->recargo) }}</td></tr>@endif
            <tr><td><strong>{{ __('facturas.total', [], $i) }}</strong></td><td class="num"><strong>{{ $e($factura->total) }}</strong></td></tr>
        </tbody></table>
    </td>
</tr></table>
</body>
</html>
