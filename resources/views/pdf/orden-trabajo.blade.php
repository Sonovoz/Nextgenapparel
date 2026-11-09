@php
    use App\Dominio\Produccion\OrdenTrabajo;
    $cm = fn (int $mm) => rtrim(rtrim(number_format($mm / 10, 1, ',', ''), '0'), ',');
    $fuentes = ['Jost Bold', 'Lora Italic', 'Anton', 'Dancing Script'];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Orden de trabajo {{ $pedido->numero }}</title>
<style>
    @page { margin: 16mm 14mm; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; color: #111; line-height: 1.35; }
    h1 { font-size: 16pt; margin: 0; }
    h2 { font-size: 11pt; margin: 6mm 0 2mm; padding-bottom: 1mm; border-bottom: 1.5px solid #111; }
    .tenue { color: #666; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; font-size: 7.5pt; text-transform: uppercase; color: #555; border-bottom: 1px solid #111; padding: 1.2mm; }
    td { border-bottom: 1px solid #e5e5e5; padding: 1.5mm 1.2mm; vertical-align: top; }
    .maquetas img { width: 60mm; margin: 0 4mm 2mm 0; border: 1px solid #ddd; }
    .linea { page-break-inside: avoid; }
    .casilla { display: inline-block; width: 3.5mm; height: 3.5mm; border: 1px solid #111; }
</style>
</head>
<body>
<table><tr>
    <td style="border: 0; padding: 0;"><h1>Orden de trabajo · {{ $pedido->numero }}</h1>
        <span class="tenue">Pedido del {{ $pedido->created_at->timezone('Europe/Madrid')->format('d/m/Y') }} · {{ $pedido->lineas->sum('unidades') }} prendas</span></td>
    <td style="border: 0; padding: 0; text-align: right;"><strong>Entrega estimada</strong><br>{{ $pedido->fecha_estimada_entrega?->format('d/m/Y') ?? '—' }}</td>
</tr></table>

@foreach ($pedido->lineas->values() as $i => $linea)
    <div class="linea">
        <h2>L{{ $i + 1 }} · {{ $linea->nombreEn('es') }}@if ($linea->colorEn('es')) · {{ $linea->colorEn('es') }}@endif @if ($linea->tipo === 'pack') (pack)@endif</h2>
        <p>
            @if ($linea->tallas)
                @foreach ($linea->tallas as $t)<strong>{{ $t['nombre'] }}</strong> × {{ $t['unidades'] }} &nbsp; @endforeach
            @else
                {{ $linea->unidades }} prendas (reparto del pack en la hoja de compra)
            @endif
        </p>
        @if (! empty($maquetas[$linea->id]))
            <div class="maquetas">@foreach ($maquetas[$linea->id] as $img)<img src="{{ $img }}" alt="">@endforeach</div>
        @endif
        <table>
            <thead><tr><th></th><th>Zona</th><th>Diseño</th><th>Medida</th><th>Posición</th><th>Calidad</th></tr></thead>
            <tbody>
                @foreach ($linea->impresiones as $impresion)
                    <tr>
                        <td style="width: 5mm;"><span class="casilla"></span></td>
                        <td>{{ $impresion->zonaEn('es') }}</td>
                        <td>@if ($impresion->texto !== null)Texto «{{ $impresion->texto }}» · {{ $fuentes[$impresion->fuente ?? 0] ?? '—' }} · tinta {{ $impresion->tinta }}@else{{ OrdenTrabajo::nombreArchivo($pedido, $i + 1, $impresion) }}@endif</td>
                        <td>{{ $cm($impresion->ancho_mm) }} × {{ $cm($impresion->alto_mm) }} cm</td>
                        <td>{{ $cm($impresion->desde_cuello_mm) }} cm bajo el cuello · {{ $impresion->desde_centro_mm === 0 ? 'centrado' : $cm(abs($impresion->desde_centro_mm)).' cm a la '.($impresion->desde_centro_mm < 0 ? 'izquierda' : 'derecha').' de quien la lleva' }}</td>
                        <td>{{ $impresion->ppp ? $impresion->ppp.' ppp ('.$impresion->nivel_calidad.')' : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endforeach
</body>
</html>
