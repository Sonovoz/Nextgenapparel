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
                    <span>{{ $p['prenda'] }} · {{ $p['unidades'] }} uds.</span><span>{{ $e($p['base']) }} sin IVA</span>
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
