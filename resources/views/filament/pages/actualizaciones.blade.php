<x-filament-panels::page>
    {{-- D236: numeradas desde la primera (la nº 1); sale de docs/HISTORIAL.md, una línea por entrega. --}}
    @if ($actualizaciones === [])
        <x-filament::section>No se encuentra el historial (docs/HISTORIAL.md) en el servidor.</x-filament::section>
    @else
        <x-filament::section>
            <table style="width: 100%; font-size: .875rem; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; opacity: .7;">
                        <th style="padding: .4rem .6rem .4rem 0; width: 4.5rem;">Nº</th>
                        <th style="padding: .4rem .6rem; width: 6.5rem;">Fecha</th>
                        <th style="padding: .4rem .6rem;">Qué trae</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($actualizaciones as $i => $a)
                        <tr style="border-top: 1px solid rgba(127,127,127,.15); {{ $i === 0 ? 'background: rgba(99,102,241,.08);' : '' }}">
                            <td style="padding: .55rem .6rem .55rem 0; font-weight: 700; white-space: nowrap;">{{ $a['numero'] }}@if ($i === 0) <span style="font-size: .7rem; font-weight: 500; opacity: .75;">instalada</span>@endif</td>
                            <td style="padding: .55rem .6rem; white-space: nowrap;">{{ $a['fecha'] }}</td>
                            <td style="padding: .55rem .6rem;">{{ $a['texto'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
    @endif
</x-filament-panels::page>
