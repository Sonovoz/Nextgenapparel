@php
    use App\Dominio\Clientes\Paises;
    use App\Dominio\Precios\Formato;
    use App\Http\Controllers\PedidoController;
    $cm = fn (int $mm) => rtrim(rtrim(number_format($mm / 10, 1, app()->getLocale() === 'en' ? '.' : ',', ''), '0'), ',.');
    $envio = $pedido->direccion_envio;
    $recargo = array_sum(array_column($pedido->desglose['pasos'][5]['detalle'] ?? [], 'cuota_recargo'));
@endphp
<x-tienda :titulo="__('pedidos.titulo', ['numero' => $pedido->numero]).' · Nextgenapparel'" :descripcion="__('pedidos.recibido')" :alternos="$alternos" :seo="['noindex' => true]">
    <section class="carrito-pagina" aria-labelledby="titulo-pedido">
        <div class="contenedor">
            <p class="etiqueta">{{ __('pedidos.recibido') }}</p>
            <h1 class="titulo-cuenta" id="titulo-pedido">{{ __('pedidos.titulo', ['numero' => $pedido->numero]) }}</h1>
            <p class="entradilla">{{ __('pedidos.estado') }}: <strong>{{ $pedido->estado->visible() }}</strong> · {{ __('pedidos.fecha') }}: {{ $pedido->created_at->locale(app()->getLocale())->isoFormat('LL') }}</p>

            @if (session('estado_pedido'))
                <p class="aviso-cuenta correcto" role="status">{{ session('estado_pedido') }}</p>
            @endif
            @unless ($pedido->correoConfirmado())
                <div class="aviso-cuenta verificar" role="status">
                    <p>{{ __('pedidos.confirma_correo', ['correo' => $pedido->correo]) }}</p>
                    <form method="POST" action="{{ PedidoController::url('reenviar', $pedido) }}">@csrf <button type="submit" class="boton-texto">{{ __('pedidos.reenviar_enlace') }}</button></form>
                </div>
            @endunless
            <p class="aviso-cuenta verificar">{{ __('pedidos.pago_pronto') }}</p>

            <div class="carrito-rejilla">
                <div class="pedido-lineas">
                    @foreach ($pedido->lineas as $linea)
                        <article class="pedido-tarjeta pedido-linea">
                            <h2>{{ $linea->nombreEn() }}@if ($linea->colorEn()) · {{ $linea->colorEn() }}@endif</h2>
                            <p class="claro">
                                @if ($linea->tallas)
                                    {{ collect($linea->tallas)->map(fn ($t) => $t['nombre'].' × '.$t['unidades'])->join(' · ') }} ·
                                @endif
                                {{ trans_choice('pedidos.unidades', $linea->unidades, ['n' => $linea->unidades]) }}
                            </p>
                            @if ($linea->impresiones->isNotEmpty())
                                <ul class="pedido-impresiones">
                                    @foreach ($linea->impresiones as $impresion)
                                        <li><strong>{{ $impresion->zonaEn() }}</strong>
                                            <span>{{ $impresion->texto !== null ? __('pedidos.texto_marca', ['texto' => $impresion->texto]) : $impresion->nombre_original }}</span>
                                            <small>{{ __('pedidos.medida', ['ancho' => $cm($impresion->ancho_mm), 'alto' => $cm($impresion->alto_mm)]) }}</small></li>
                                    @endforeach
                                </ul>
                            @endif
                            <p class="pedido-importe">{{ Formato::euros($linea->neto) }} <small>{{ __('pedidos.sin_iva') }}</small></p>
                        </article>
                    @endforeach

                    <div class="pedido-tarjeta pedido-datos">
                        <div>
                            <h2>{{ __('pedidos.enviar_a') }}</h2>
                            <p class="claro">{{ $envio['nombre'] }}@if ($envio['empresa']) · {{ $envio['empresa'] }}@endif<br>
                                {{ $envio['direccion'] }}@if ($envio['direccion_2']), {{ $envio['direccion_2'] }}@endif<br>
                                {{ $envio['codigo_postal'] }} {{ $envio['localidad'] }}@if ($envio['provincia']) ({{ $envio['provincia'] }})@endif · {{ Paises::disponibles()[$envio['pais']] ?? $envio['pais'] }}<br>
                                {{ $envio['telefono'] }}</p>
                        </div>
                        <div>
                            <h2>{{ __('pedidos.datos_fiscales') }}</h2>
                            <p class="claro">{{ $pedido->nombre_fiscal }}@if ($pedido->nif) · {{ $pedido->nif }}@endif<br>{{ $pedido->correo }}</p>
                        </div>
                    </div>
                </div>

                <aside class="carrito-resumen" aria-labelledby="titulo-resumen-pedido">
                    <h2 id="titulo-resumen-pedido">{{ __('pedidos.resumen') }}</h2>
                    <dl>
                        <div><dt>{{ __('carrito.prendas_impresion') }}</dt><dd>{{ Formato::euros($pedido->bruto) }}</dd></div>
                        @if ($pedido->descuentos > 0)
                            <div class="descuento"><dt>{{ $pedido->desglose['pasos'][2]['detalle']['nombre'] ?? __('carrito.descuentos') }}{{ $pedido->cupon ? ' · '.$pedido->cupon : '' }}</dt><dd>−{{ Formato::euros($pedido->descuentos) }}</dd></div>
                        @endif
                        <div><dt>{{ __('carrito.envio') }}</dt><dd>{{ Formato::euros($pedido->envio) }}</dd></div>
                        <div><dt>{{ __('carrito.base') }}</dt><dd>{{ Formato::euros($pedido->base_imponible) }}</dd></div>
                        <div><dt>{{ __('carrito.iva') }}</dt><dd>{{ Formato::euros($pedido->impuestos - $recargo) }}</dd></div>
                        @if ($recargo > 0)<div><dt>{{ __('carrito.recargo') }}</dt><dd>{{ Formato::euros($recargo) }}</dd></div>@endif
                    </dl>
                    <p class="cuenta-total"><span>{{ __('carrito.total') }}</span><strong>{{ Formato::euros($pedido->total) }}</strong></p>
                </aside>
            </div>
        </div>
    </section>
</x-tienda>
