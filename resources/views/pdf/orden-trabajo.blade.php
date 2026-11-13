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
@include('pdf._estilos')
    .maquetas img { width: 56mm; margin: 0 4mm 2mm 0; border: 0.3mm solid #E6E6EC; }
    .linea { page-break-inside: avoid; }
    .linea h2 { color: #0B0B10; font-size: 9.5pt; letter-spacing: 0; text-transform: none; border-bottom: 0.3mm solid #E6E6EC; padding-bottom: 1.5mm; }
    .casilla { display: inline-block; width: 3.5mm; height: 3.5mm; border: 0.3mm solid #0B0B10; }
</style>
</head>
<body>
@include('pdf._pie', ['texto' => 'Orden de trabajo · '.$pedido->numero.' · uso interno'])

@include('pdf._cabecera', ['tipo' => 'Orden de trabajo', 'numero' => $pedido->numero, 'datos' => [
    'Pedido del '.$pedido->created_at->timezone('Europe/Madrid')->format('d/m/Y').' · '.$pedido->lineas->sum('unidades').' prendas',
    '<strong style="color: #FFFFFF;">Entrega estimada: '.e($pedido->fecha_estimada_entrega?->format('d/m/Y') ?? '—').'</strong>',
]])

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
        <table class="lista">
            <thead><tr><th></th><th>Zona</th><th>Diseño</th><th>Medida</th><th>Posición</th><th>Calidad</th></tr></thead>
            <tbody>
                @foreach ($linea->impresiones as $impresion)
                    <tr>
                        <td style="width: 5mm;"><span class="casilla"></span></td>
                        <td>{{ $impresion->zonaEn('es') }}</td>
                        <td>@if ($impresion->texto !== null)Texto «{{ $impresion->texto }}» · {{ $fuentes[$impresion->fuente ?? 0] ?? '—' }} · tinta {{ $impresion->tinta }}@else{{ OrdenTrabajo::nombreArchivo($pedido, $i + 1, $impresion) }}@endif</td>
                        <td>{{ $cm($impresion->ancho_mm) }} × {{ $cm($impresion->alto_mm) }} cm</td>
                        <td>{{ $cm($impresion->desde_cuello_mm) }} cm bajo el cuello · {{ $impresion->desde_centro_mm === 0 ? 'centrado' : $cm(abs($impresion->desde_centro_mm)).' cm a la '.($impresion->desde_centro_mm < 0 ? 'izquierda' : 'derecha').' de quien la lleva' }}</td>
                        <td>{{ $impresion->ppp ? $impresion->ppp.' ppp ('.$impresion->nivel_calidad.')' : '—' }}@if ($impresion->ppp_archivo)<br><span class="tenue">archivo: {{ $impresion->ppp_archivo }} ppp</span>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if ($linea->tipo === 'pack' && is_array($linea->elecciones))
            {{-- D223: en un pack, cada prenda lleva solo las zonas que ofrece. --}}
            @php $nombresZona = $linea->impresiones->mapWithKeys(fn ($i) => [$i->zona_impresion_id => $i->zonaEn('es')]); @endphp
            <p class="tenue">Zonas por prenda:
                @foreach ($linea->elecciones as $e)
                    <strong>{{ \App\Models\Prenda::find($e['prenda'] ?? 0)?->nombre ?? 'Prenda' }}</strong>: {{ isset($e['zonas']) ? collect($e['zonas'])->map(fn ($z) => $nombresZona[$z] ?? '?')->implode(', ') : 'todas' }}@if (! $loop->last) · @endif
                @endforeach
            </p>
        @endif
    </div>
@endforeach
</body>
</html>
