@php
    use App\Dominio\Clientes\Paises;
    use App\Dominio\Precios\Formato;
    use App\Http\Controllers\PedidoController;
    $cm = fn (int $mm) => rtrim(rtrim(number_format($mm / 10, 1, app()->getLocale() === 'en' ? '.' : ',', ''), '0'), ',.');
    $envio = $pedido->direccion_envio;
    $recargo = array_sum(array_column($pedido->desglose['pasos'][5]['detalle'] ?? [], 'cuota_recargo'));
    use App\Dominio\Pedidos\EstadoPedido as E;
    // Línea de progreso (6.6): cada hito con la fecha en que se alcanzó, según el historial.
    $alcanzado = fn (E $e) => $pedido->historial->first(fn ($h) => $h->estado === $e)?->created_at;
    $cancelado = in_array($pedido->estado, [E::Cancelado, E::Reembolsado], true);
    $hitos = [['pedido', $pedido->created_at], ['pagado', $alcanzado(E::Pagado)]];
    $hitos = $cancelado
        ? [...$hitos, ['cancelado', $alcanzado($pedido->estado) ?? $pedido->updated_at]]
        : [...$hitos, ['produccion', $alcanzado(E::EnProduccion)], ['enviado', $alcanzado(E::Enviado)], ['entregado', $alcanzado(E::Entregado)]];
    $fecha = fn ($f) => $f?->locale(app()->getLocale())->isoFormat('D MMM');
@endphp
<x-tienda :titulo="__('pedidos.titulo', ['numero' => $pedido->numero]).' · Nextgenapparel'" :descripcion="__('pedidos.recibido')" :alternos="$alternos" :seo="['noindex' => true]">
    <section class="carrito-pagina" aria-labelledby="titulo-pedido">
        <div class="contenedor">
            <p class="etiqueta">{{ __('pedidos.recibido') }}</p>
            <h1 class="titulo-cuenta" id="titulo-pedido">{{ __('pedidos.titulo', ['numero' => $pedido->numero]) }}</h1>
            <p class="entradilla">{{ __('pedidos.estado') }}: <strong>{{ $pedido->estado->visible() }}</strong> · {{ __('pedidos.fecha') }}: {{ $pedido->created_at->locale(app()->getLocale())->isoFormat('LL') }}
                @if ($pedido->fecha_estimada_entrega) · {{ __('pedidos.entrega_estimada') }}: <strong>{{ $pedido->fecha_estimada_entrega->locale(app()->getLocale())->isoFormat('LL') }}</strong>@endif
                @if ($pedido->numero_seguimiento)<br>{{ __('pedidos.seguimiento', ['transportista' => $pedido->transportista, 'numero' => $pedido->numero_seguimiento]) }}@endif</p>

            @if (session('estado_pedido'))
                <p class="aviso-cuenta correcto" role="status">{{ session('estado_pedido') }}</p>
            @endif
            @unless ($pedido->correoConfirmado())
                <div class="aviso-cuenta verificar" role="status">
                    <p>{{ __('pedidos.confirma_correo', ['correo' => $pedido->correo]) }}</p>
                    <form method="POST" action="{{ PedidoController::url('reenviar', $pedido) }}">@csrf <button type="submit" class="boton-texto">{{ __('pedidos.reenviar_enlace') }}</button></form>
                </div>
            @endunless
            @if ($pedido->estado === E::PendientePago)
                <p class="aviso-cuenta verificar">{{ __('pedidos.pago_pronto') }}</p>
            @endif
            @if ($pedido->cancelacion_solicitada_en && ! $cancelado)
                <p class="aviso-cuenta verificar" role="status">{{ __('pedidos.cancelacion_pedida', ['fecha' => $pedido->cancelacion_solicitada_en->locale(app()->getLocale())->isoFormat('LL')]) }}</p>
            @endif

            <section class="pedido-progreso" aria-labelledby="titulo-progreso">
                <h2 id="titulo-progreso" class="oculto">{{ __('pedidos.progreso') }}</h2>
                <ol>
                    @foreach ($hitos as [$clave, $cuando])
                        <li @class(['hecho' => $cuando !== null, 'cancelado' => $clave === 'cancelado']) @if ($cuando !== null && $loop->last || ($cuando !== null && ($hitos[$loop->index + 1][1] ?? null) === null)) aria-current="step" @endif>
                            <span>{{ __('pedidos.paso_'.$clave) }}</span>
                            @if ($cuando)<time datetime="{{ $cuando->toIso8601String() }}">{{ $fecha($cuando) }}</time>@endif
                        </li>
                    @endforeach
                </ol>
            </section>
            @if ($pedido->facturas->isNotEmpty())
                <section class="pedido-novedades" aria-labelledby="titulo-facturas">
                    <h2 id="titulo-facturas">{{ __('facturas.facturas') }}</h2>
                    <ul>
                        @foreach ($pedido->facturas as $factura)
                            <li><a href="{{ route('pedidos.'.app()->getLocale().'.factura', ['pedido' => $pedido->uuid, 'factura' => $factura->uuid]) }}">{{ __('facturas.descargar', ['codigo' => $factura->codigo]) }}</a>
                                <time>{{ $factura->tipo->etiqueta() }} · {{ $factura->emitida_en->locale(app()->getLocale())->isoFormat('LL') }}</time></li>
                        @endforeach
                    </ul>
                </section>
            @endif
            @php $novedades = $pedido->notas()->where('tipo', 'cliente')->get(); @endphp
            @if ($novedades->isNotEmpty())
                <section class="pedido-novedades" aria-labelledby="titulo-novedades">
                    <h2 id="titulo-novedades">{{ __('pedidos.novedades') }}</h2>
                    <ul>
                        @foreach ($novedades as $nota)
                            <li><time datetime="{{ $nota->created_at->toIso8601String() }}">{{ $nota->created_at->locale(app()->getLocale())->isoFormat('LL') }}</time> {{ $nota->texto }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($pedido->incidencias->isNotEmpty() || $puedeIncidencia)
                <section class="pedido-novedades pedido-incidencias" id="incidencias" aria-labelledby="titulo-incidencias">
                    <h2 id="titulo-incidencias">{{ __('incidencias.titulo') }}</h2>
                    @foreach ($pedido->incidencias as $incidencia)
                        <article class="incidencia">
                            <p><strong>{{ $incidencia->codigo }}</strong> · {{ $incidencia->motivo->etiqueta() }}
                                <time datetime="{{ $incidencia->created_at->toIso8601String() }}">{{ $incidencia->created_at->locale(app()->getLocale())->isoFormat('LL') }} ·
                                    {{ $incidencia->abierta() ? __('incidencias.estado_abierta') : __('incidencias.estado_resuelta').' · '.$incidencia->resolucion?->etiqueta() }}
                                    @if ($incidencia->importe_reembolso) ({{ Formato::euros($incidencia->importe_reembolso) }})@endif</time></p>
                            <p class="claro">{{ $incidencia->descripcion }}</p>
                            @if ($incidencia->fotos->isNotEmpty())
                                <div class="incidencia-fotos">
                                    @foreach ($incidencia->fotos as $foto)
                                        <a href="{{ route('pedidos.'.app()->getLocale().'.foto-incidencia', ['pedido' => $pedido->uuid, 'foto' => $foto->id]) }}" target="_blank" rel="noopener">
                                            <img src="{{ route('pedidos.'.app()->getLocale().'.foto-incidencia', ['pedido' => $pedido->uuid, 'foto' => $foto->id]) }}" alt="{{ __('incidencias.foto_alt', ['n' => $loop->iteration, 'codigo' => $incidencia->codigo]) }}" width="{{ $foto->ancho_px }}" height="{{ $foto->alto_px }}" loading="lazy"></a>
                                    @endforeach
                                </div>
                            @endif
                            @if ($incidencia->respuesta)
                                <p><strong>{{ __('incidencias.respuesta') }}:</strong> {{ $incidencia->respuesta }}</p>
                            @endif
                        </article>
                    @endforeach
                    @if ($puedeIncidencia)
                        <details class="pedido-cancelar" @if ($errors->has('incidencia') || $errors->has('motivo') || $errors->has('descripcion') || $errors->has('fotos') || $errors->has('fotos.*')) open @endif>
                            <summary>{{ __('incidencias.abrir') }}</summary>
                            <form method="POST" action="{{ route('pedidos.'.app()->getLocale().'.incidencia', ['pedido' => $pedido->uuid]) }}" enctype="multipart/form-data">@csrf
                                <p class="claro">{{ __('incidencias.abrir_texto', ['fecha' => $plazoIncidencia?->locale(app()->getLocale())->isoFormat('LL')]) }}</p>
                                @if ($errors->any())
                                    <div class="aviso-cuenta error" role="alert"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                                @endif
                                <label for="motivo-incidencia">{{ __('incidencias.motivo') }}</label>
                                <select id="motivo-incidencia" name="motivo" required>
                                    @foreach (\App\Dominio\Incidencias\MotivoIncidencia::cases() as $motivo)
                                        <option value="{{ $motivo->value }}" @selected(old('motivo') === $motivo->value)>{{ $motivo->etiqueta() }}</option>
                                    @endforeach
                                </select>
                                <label for="descripcion-incidencia">{{ __('incidencias.descripcion') }}</label>
                                <textarea id="descripcion-incidencia" name="descripcion" rows="4" maxlength="2000" minlength="10" required>{{ old('descripcion') }}</textarea>
                                <label for="fotos-incidencia">{{ __('incidencias.fotos', ['n' => \App\Dominio\Incidencias\AbrirIncidencia::MAX_FOTOS]) }}</label>
                                <input id="fotos-incidencia" type="file" name="fotos[]" accept="image/jpeg,image/png,image/webp" multiple aria-describedby="fotos-ayuda">
                                <small id="fotos-ayuda" class="claro">{{ __('incidencias.fotos_ayuda') }}</small>
                                <button type="submit" class="boton">{{ __('incidencias.enviar') }}</button>
                            </form>
                        </details>
                    @endif
                </section>
            @endif

            <div class="carrito-rejilla">
                <div class="pedido-lineas">
                    @foreach ($pedido->lineas as $linea)
                        <article class="pedido-tarjeta pedido-linea">
                            @if ($linea->maquetas->isNotEmpty())
                                <div class="pedido-maquetas">
                                    @foreach ($linea->maquetas as $maqueta)
                                        <img src="{{ route('pedidos.'.app()->getLocale().'.maqueta', ['pedido' => $pedido->uuid, 'maqueta' => $maqueta->id]) }}" alt="{{ __('pedidos.maqueta_'.$maqueta->cara, ['prenda' => $linea->nombreEn()]) }}" width="{{ $maqueta->ancho_px }}" height="{{ $maqueta->alto_px }}" loading="lazy">
                                    @endforeach
                                </div>
                            @endif
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

                    <div class="pedido-acciones">
                        <h2>{{ __('pedidos.acciones') }}</h2>
                        <form method="POST" action="{{ PedidoController::url('repetir', $pedido) }}">@csrf
                            <button type="submit" class="boton">{{ __('pedidos.repetir') }}</button>
                            <p class="claro">{{ __('pedidos.repetir_nota') }}</p>
                        </form>
                        @if ($puedeCancelar)
                            @php $sinPagar = $pedido->estado === E::PendientePago; @endphp
                            <details class="pedido-cancelar">
                                <summary>{{ __($sinPagar ? 'pedidos.cancelar' : 'pedidos.cancelar_pedir') }}</summary>
                                <form method="POST" action="{{ PedidoController::url('cancelar', $pedido) }}" onsubmit="return confirm(@js(__('pedidos.cancelar_confirmar')))">@csrf
                                    <p class="claro">{{ __($sinPagar ? 'pedidos.cancelar_nota_sin_pagar' : 'pedidos.cancelar_nota_pagado') }}</p>
                                    <label for="motivo-cancelar">{{ __('pedidos.cancelar_motivo') }}</label>
                                    <textarea id="motivo-cancelar" name="motivo" rows="3" maxlength="500"></textarea>
                                    <button type="submit" class="boton-texto">{{ __($sinPagar ? 'pedidos.cancelar' : 'pedidos.cancelar_pedir') }}</button>
                                </form>
                            </details>
                        @endif
                    </div>
                </aside>
            </div>
        </div>
    </section>
</x-tienda>
