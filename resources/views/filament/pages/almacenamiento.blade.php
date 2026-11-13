@php use App\Dominio\Sistema\Almacenamiento\Almacenamiento as M; @endphp
<x-filament-panels::page>
    {{-- D231: espacio del proyecto frente al máximo elegido. --}}
    <x-filament::section>
        <x-slot name="heading">Espacio del proyecto</x-slot>
        @php $color = $porcentaje >= $falloPct ? '#dc2626' : ($porcentaje >= $avisoPct ? '#d97706' : '#16a34a'); @endphp
        <p style="margin: 0 0 .5rem; font-size: 1.5rem; font-weight: 700;">{{ M::legible($total) }} <span style="font-size: .95rem; font-weight: 400; opacity: .7;">de {{ M::legible($limite) }} ({{ $porcentaje }} %)</span></p>
        <div style="height: 12px; border-radius: 99px; background: rgba(127,127,127,.2); overflow: hidden;">
            <div style="height: 100%; width: {{ min(100, $porcentaje) }}%; background: {{ $color }}; border-radius: inherit;"></div>
        </div>
        <p style="margin: .6rem 0 0; font-size: .85rem; opacity: .75;">
            Aviso al {{ $avisoPct }} % y fallo al {{ $falloPct }} % en «Estado del sistema» (con correo).
            @if ($libre !== null) Libre en el disco del servidor: {{ M::legible($libre) }}. @endif
        </p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Qué ocupa</x-slot>
        <table style="width: 100%; font-size: .875rem; border-collapse: collapse;">
            @foreach ($partes as $p)
                <tr style="border-bottom: 1px solid rgba(127,127,127,.15);">
                    <td style="padding: .55rem 0;"><strong>{{ $p['nombre'] }}</strong>@if ($p['nota'])<br><span style="opacity: .65;">{{ $p['nota'] }}</span>@endif</td>
                    <td style="padding: .55rem 0; text-align: right; white-space: nowrap;">{{ M::legible($p['bytes']) }}</td>
                    <td style="padding: .55rem 0 .55rem 1rem; width: 30%;">
                        <div style="height: 6px; border-radius: 99px; background: rgba(127,127,127,.18);"><div style="height: 100%; width: {{ $total > 0 ? round($p['bytes'] / $total * 100) : 0 }}%; background: #6366f1; border-radius: inherit;"></div></div>
                    </td>
                </tr>
            @endforeach
        </table>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Reglas de limpieza</x-slot>
        <ul style="margin: 0; padding-left: 1.1rem; font-size: .875rem; line-height: 1.7;">
            <li>Diseños sin venta: se borran solos a las {{ (int) \App\Models\Ajuste::obtener('disenos.horas_sin_venta') }} h (cada hora). Botón «Borrar archivos sin venta» para hacerlo ya.</li>
            <li>Originales de los pedidos: {{ (int) \App\Models\Ajuste::obtener('pedidos.dias_archivos') }} días hábiles desde el envío (cada noche).
                @if ($proximos > 0) <strong>{{ $proximos }} pedidos los borran en los próximos {{ \App\Dominio\Sistema\Almacenamiento\ZipArchivosPedidos::DIAS_AVISO }} días:</strong> descárgalos con «ZIP de originales que se borran pronto». @endif</li>
            <li>Plazos en Ajustes → General. La salud del sistema avisa si una limpieza deja de ejecutarse.</li>
            <li>Copia de seguridad en ZIP (base de datos y archivos): cada noche.
                @if ($ultimaCopia) La última es del {{ $ultimaCopia['fecha']->format('d/m/Y H:i') }} ({{ M::legible($ultimaCopia['bytes']) }}). @endif
                <a href="{{ $urlCopias }}" style="text-decoration: underline;">Descargarla o hacer una ahora</a>.</li>
        </ul>
    </x-filament::section>
</x-filament-panels::page>
