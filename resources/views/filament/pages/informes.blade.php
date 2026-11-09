@php $e = fn (int $c) => \App\Dominio\Precios\Formato::euros($c, 'es'); @endphp
<x-filament-panels::page>
    <div style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: end;">
        <label style="display: grid; gap: .25rem; font-size: .875rem;">Desde <input type="date" wire:model.live="desde" style="border: 1px solid rgba(127,127,127,.4); border-radius: .5rem; padding: .4rem .6rem; background: transparent;"></label>
        <label style="display: grid; gap: .25rem; font-size: .875rem;">Hasta <input type="date" wire:model.live="hasta" style="border: 1px solid rgba(127,127,127,.4); border-radius: .5rem; padding: .4rem .6rem; background: transparent;"></label>
        <span style="font-size: .8rem; opacity: .7;">Sale de las facturas emitidas en el periodo: cuadra con la contabilidad.</span>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem;">
        @foreach ([['Facturado', $e($datos['facturado'])], ['Rectificado', '−'.$e($datos['rectificado'])], ['Neto', $e($datos['neto'])], ['Pedidos facturados', $datos['pedidos']], ['Ticket medio', $e($datos['ticket'])], ['IVA neto', $e($datos['iva'])], ['Recargo neto', $e($datos['recargo'])], ['Base neta', $e($datos['base'])]] as [$etiqueta, $valor])
            <x-filament::section compact>
                <div style="font-size: .8rem; opacity: .7;">{{ $etiqueta }}</div>
                <div style="font-size: 1.4rem; font-weight: 700;">{{ $valor }}</div>
            </x-filament::section>
        @endforeach
    </div>

    <x-filament::section>
        <x-slot name="heading">Márgenes</x-slot>
        <x-slot name="description">Pedidos facturados en el periodo y no cancelados: lo vendido sin IVA ni envío menos el coste de las prendas lisas y de la impresión (Productos).@if ($datos['margen']['estimadas'] > 0) {{ $datos['margen']['estimadas'] }} líneas de antes de guardar el coste se estiman con el coste actual.@endif</x-slot>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem;">
            @foreach ([['Vendido sin IVA', $e($datos['margen']['ingresos'])], ['Coste', $e($datos['margen']['coste'])], ['Margen', $e($datos['margen']['margen'])], ['Margen %', number_format($datos['margen']['porcentaje'] / 100, 1, ',', '.').' %']] as [$etiqueta, $valor])
                <div><div style="font-size: .8rem; opacity: .7;">{{ $etiqueta }}</div><div style="font-size: 1.4rem; font-weight: 700;">{{ $valor }}</div></div>
            @endforeach
        </div>
    </x-filament::section>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1rem;">
        <x-filament::section>
            <x-slot name="heading">Ventas por día</x-slot>
            @forelse ($datos['dias'] as $d)
                <div style="display: flex; justify-content: space-between; font-size: .875rem; padding: .3rem 0; border-bottom: 1px solid rgba(127,127,127,.15);">
                    <span>{{ \Carbon\Carbon::parse($d['dia'])->format('d/m/Y') }} · {{ $d['pedidos'] }} {{ $d['pedidos'] === 1 ? 'pedido' : 'pedidos' }}</span><strong>{{ $e($d['total']) }}</strong>
                </div>
            @empty
                <p style="opacity: .7; font-size: .875rem;">Sin facturas en el periodo.</p>
            @endforelse
        </x-filament::section>
        <x-filament::section>
            <x-slot name="heading">Lo más vendido</x-slot>
            @forelse ($datos['prendas'] as $p)
                <div style="display: flex; justify-content: space-between; font-size: .875rem; padding: .3rem 0; border-bottom: 1px solid rgba(127,127,127,.15);">
                    <span>{{ $p['prenda'] }} · {{ $p['unidades'] }} uds.</span><span>{{ $e($p['base']) }} · margen {{ $e($p['margen']) }}</span>
                </div>
            @empty
                <p style="opacity: .7; font-size: .875rem;">—</p>
            @endforelse
        </x-filament::section>
        <x-filament::section>
            <x-slot name="heading">Cupones usados</x-slot>
            @forelse ($datos['cupones'] as $c)
                <div style="display: flex; justify-content: space-between; font-size: .875rem; padding: .3rem 0;"><span>{{ $c['cupon'] }}</span><span>{{ $c['pedidos'] }} {{ $c['pedidos'] === 1 ? 'pedido' : 'pedidos' }}</span></div>
            @empty
                <p style="opacity: .7; font-size: .875rem;">Ninguno.</p>
            @endforelse
        </x-filament::section>
    </div>
</x-filament-panels::page>
