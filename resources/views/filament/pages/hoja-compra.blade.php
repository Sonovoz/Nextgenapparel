<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Prendas lisas para los pedidos pagados</x-slot>
        <x-slot name="description">Pedidos pagados, con el diseño en revisión o pendientes de cambios. «Comprar» descuenta el stock de cada variante.</x-slot>

        @if ($filas === [])
            <p style="opacity: 0.7;">No hay pedidos pagados pendientes de producir.</p>
        @else
            <div style="overflow-x: auto;">
                <table style="width: 100%; font-size: 0.875rem; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align: left; opacity: 0.7;">
                            <th style="padding: 0.5rem;">Proveedor</th>
                            <th style="padding: 0.5rem;">Prenda</th>
                            <th style="padding: 0.5rem;">Color</th>
                            <th style="padding: 0.5rem;">Talla</th>
                            <th style="padding: 0.5rem;">SKU</th>
                            <th style="padding: 0.5rem; text-align: right;">Necesarias</th>
                            <th style="padding: 0.5rem; text-align: right;">Stock</th>
                            <th style="padding: 0.5rem; text-align: right;">Comprar</th>
                            <th style="padding: 0.5rem;">Pedidos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($filas as $f)
                            <tr style="border-top: 1px solid rgba(127,127,127,.2);">
                                <td style="padding: 0.5rem;">{{ $f['proveedor'] }}</td>
                                <td style="padding: 0.5rem;">{{ $f['prenda'] }}@if ($f['referencia'])<br><span style="opacity: 0.6;">{{ $f['referencia'] }}</span>@endif</td>
                                <td style="padding: 0.5rem;">{{ $f['color'] }}</td>
                                <td style="padding: 0.5rem;">{{ $f['talla'] }}</td>
                                <td style="padding: 0.5rem;">{{ $f['sku'] ?? '—' }}</td>
                                <td style="padding: 0.5rem; text-align: right;">{{ $f['necesarias'] }}</td>
                                <td style="padding: 0.5rem; text-align: right;">{{ $f['stock'] }}</td>
                                <td style="padding: 0.5rem; text-align: right; font-weight: 700;">{{ $f['comprar'] }}</td>
                                <td style="padding: 0.5rem;">{{ implode(', ', $f['pedidos']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
