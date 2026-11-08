@php $e = fn (?int $c) => \App\Filament\Concerns\CampoDinero::formatear($c); $p = fn (int $c) => \App\Filament\Concerns\CampoPorcentaje::formatear($c); @endphp
<x-filament-panels::page>
    <form wire:submit="calcular">
        {{ $this->form }}
    </form>

    @if ($resultado)
        <x-filament::section heading="Resultado" :description="$resultado['confirmable'] ? 'Se puede confirmar.' : 'No se puede confirmar.'">
            @foreach ($resultado['impedimentos'] as $texto)
                <p style="color: #DC2626; margin: 0 0 .25rem;">✕ {{ $texto }}</p>
            @endforeach
            @foreach ($resultado['avisos'] as $texto)
                <p style="color: #059669; margin: 0 0 .25rem;">• {{ $texto }}</p>
            @endforeach

            <table style="width: 100%; margin-top: 1rem; font-size: .875rem; border-collapse: collapse;" data-simulador>
                <thead>
                    <tr style="text-align: left; border-bottom: 1px solid #E5E7EB;">
                        <th>Línea</th><th>Uds.</th><th>Prendas</th><th>Impresión</th><th>Oferta</th><th>Cupón</th><th>Neto</th><th>IVA</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($resultado['lineas'] as $linea)
                        <tr style="border-bottom: 1px solid #F3F4F6;">
                            <td>{{ $linea['nombre'] }} @if ($linea['tipo'] === 'pack')<small>(pack)</small>@endif</td>
                            <td>{{ $linea['unidades'] }}</td>
                            <td>{{ $e($linea['prendas']) }}</td>
                            <td>{{ $e($linea['impresion']) }}</td>
                            <td>{{ $linea['descuento'] ? '−'.$e($linea['descuento']) : '—' }}</td>
                            <td>{{ $linea['cupon'] ? '−'.$e($linea['cupon']) : '—' }}</td>
                            <td><strong>{{ $e($linea['neto']) }}</strong></td>
                            <td>{{ $p($linea['tipo_iva']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <dl style="display: grid; grid-template-columns: max-content auto; gap: .25rem 2rem; margin-top: 1rem; font-size: .875rem;">
                <dt>Prendas e impresión</dt><dd>{{ $e($resultado['bruto']) }} ({{ $resultado['unidades'] }} prendas)</dd>
                @if ($resultado['oferta'])<dt>Oferta «{{ $resultado['oferta']['nombre'] }}»</dt><dd>−{{ $e($resultado['oferta']['importe']) }}</dd>@endif
                @if ($resultado['cupon'])<dt>Cupón {{ $resultado['cupon']['codigo'] }}</dt><dd>−{{ $e($resultado['cupon']['importe']) }}</dd>@endif
                <dt>Subtotal sin IVA</dt><dd>{{ $e($resultado['subtotal']) }}</dd>
                @if ($resultado['envio'])
                    <dt>Envío ({{ $resultado['envio']['zona'] }}, {{ number_format($resultado['envio']['peso_gramos'] / 1000, 2, ',', '') }} kg{{ $resultado['envio']['bultos'] ? ', '.$resultado['envio']['bultos'].' cajas' : '' }}, {{ $resultado['envio']['plazo_dias'] }} días)</dt>
                    <dd>{{ $e($resultado['envio']['importe']) }}</dd>
                @endif
                @foreach ($resultado['impuestos'] as $impuesto)
                    <dt>IVA {{ $p($impuesto['tipo_iva']) }} sobre {{ $e($impuesto['base']) }}</dt><dd>{{ $e($impuesto['cuota']) }}</dd>
                    @if ($impuesto['cuota_recargo'])<dt>Recargo {{ $p($impuesto['recargo']) }}</dt><dd>{{ $e($impuesto['cuota_recargo']) }}</dd>@endif
                @endforeach
                <dt><strong>Total</strong></dt><dd><strong data-total>{{ $e($resultado['total']) }}</strong></dd>
            </dl>
        </x-filament::section>
    @endif
</x-filament-panels::page>
