@php
    use App\Dominio\Clientes\TipoCliente;
    use App\Dominio\Precios\Formato;
    use App\Http\Controllers\CarritoController;
    use App\Support\RutaCuenta;
    $r = $valoracion->resultado;
    $v = fn (string $campo) => old($campo, $datos[$campo] ?? $previa[$campo] ?? '');
    $recargo = array_sum(array_column($r->impuestos, 'cuota_recargo'));
@endphp
<x-tienda :titulo="__('carrito.cierre_titulo').' · Nextgenapparel'" :descripcion="__('carrito.cierre_entradilla')" :alternos="$alternos" :seo="['noindex' => true]">
    <section class="carrito-pagina" aria-labelledby="titulo-cierre">
        <div class="contenedor">
            <h1 class="titulo-cuenta" id="titulo-cierre">{{ __('carrito.cierre_titulo') }}</h1>
            <p class="entradilla">{{ __('carrito.cierre_entradilla') }}</p>
            @if ($errors->any())
                <div class="aviso-cuenta error" role="alert" tabindex="-1" id="resumen-errores"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                <script>document.getElementById('resumen-errores').focus();</script>
            @endif

            <div class="carrito-rejilla">
                @if ($bloqueo)
                    <div class="aviso-cuenta error" role="alert">
                        <p>{{ __('carrito.bloqueo_'.$bloqueo) }}</p>
                        @if ($bloqueo === 'correo')
                            <form method="POST" action="{{ RutaCuenta::url('reenviar') }}">@csrf <button type="submit" class="boton-texto">{{ __('carrito.reenviar') }}</button></form>
                        @elseif ($bloqueo === 'fiscal')
                            <a href="{{ RutaCuenta::url('datos') }}">{{ __('carrito.completar_datos') }}</a>
                        @endif
                    </div>
                @else
                    <form class="formulario-cuenta carrito-linea cierre-formulario" method="POST" action="{{ CarritoController::url('cerrar.calcular') }}" novalidate>
                        @csrf
                        <h2>{{ __('carrito.tus_datos') }}</h2>
                        @if ($cliente)
                            <p class="claro">{{ __('carrito.datos_de_cuenta', ['nombre' => $cliente->nombre, 'correo' => $cliente->correo]) }}
                                {{ $cliente->nif ? '· '.$cliente->nif : '' }}{{ $cliente->recargo_equivalencia ? ' · '.__('carrito.con_recargo') : '' }}
                                <a href="{{ RutaCuenta::url('datos') }}">{{ __('carrito.cambiar_datos') }}</a></p>
                        @else
                            <p class="claro"><a href="{{ RutaCuenta::url('entrar') }}">{{ __('carrito.ya_tienes_cuenta') }}</a></p>
                            @include('cuenta._campo', ['nombre' => 'correo', 'etiqueta' => __('cuenta.correo'), 'tipo' => 'email', 'autocompletar' => 'email', 'valor' => $v('correo')])
                            <fieldset class="grupo-cuenta" id="grupo-tipo">
                                <legend>{{ __('cuenta.tipo') }}</legend>
                                <div class="opciones-tipo">
                                    @foreach (TipoCliente::cases() as $tipo)
                                        <label><input type="radio" name="tipo" value="{{ $tipo->value }}" @checked($v('tipo') === $tipo->value || ($v('tipo') === '' && $tipo === TipoCliente::Particular))> <span>{{ $tipo->getLabel() }}</span></label>
                                    @endforeach
                                </div>
                            </fieldset>
                            @include('cuenta._campo', ['nombre' => 'nif', 'etiqueta' => __('cuenta.nif'), 'tipo' => 'text', 'autocompletar' => 'off', 'valor' => $v('nif'), 'opcional' => true, 'ayuda' => __('carrito.nif_ayuda')])
                            @if ($recargoInvitado)
                                <label class="casilla"><input type="checkbox" name="recargo" value="1" @checked(old('recargo', $datos['recargo'] ?? false))> {{ __('cuenta.recargo_casilla') }}</label>
                            @endif
                        @endif

                        <h2>{{ __('carrito.direccion_envio') }}</h2>
                        <p class="claro">{{ __('carrito.misma_facturacion') }}</p>
                        @include('cuenta._campo', ['nombre' => 'nombre', 'etiqueta' => __('cuenta.nombre'), 'tipo' => 'text', 'autocompletar' => 'name', 'valor' => $v('nombre')])
                        @include('cuenta._campo', ['nombre' => 'empresa', 'etiqueta' => __('cuenta.empresa'), 'tipo' => 'text', 'autocompletar' => 'organization', 'valor' => $v('empresa'), 'opcional' => true])
                        @include('cuenta._campo', ['nombre' => 'direccion', 'etiqueta' => __('cuenta.direccion'), 'tipo' => 'text', 'autocompletar' => 'address-line1', 'valor' => $v('direccion')])
                        @include('cuenta._campo', ['nombre' => 'direccion_2', 'etiqueta' => __('cuenta.direccion_2'), 'tipo' => 'text', 'autocompletar' => 'address-line2', 'valor' => $v('direccion_2'), 'opcional' => true])
                        <div class="campos-dos">
                            @include('cuenta._campo', ['nombre' => 'codigo_postal', 'etiqueta' => __('cuenta.codigo_postal'), 'tipo' => 'text', 'autocompletar' => 'postal-code', 'valor' => $v('codigo_postal')])
                            @include('cuenta._campo', ['nombre' => 'localidad', 'etiqueta' => __('cuenta.localidad'), 'tipo' => 'text', 'autocompletar' => 'address-level2', 'valor' => $v('localidad')])
                        </div>
                        <div class="campos-dos">
                            @include('cuenta._campo', ['nombre' => 'provincia', 'etiqueta' => __('cuenta.provincia'), 'tipo' => 'text', 'autocompletar' => 'address-level1', 'valor' => $v('provincia'), 'opcional' => true])
                            <div class="campo-cuenta">
                                <label for="campo-pais">{{ __('cuenta.pais') }}</label>
                                <select id="campo-pais" name="pais" autocomplete="country">
                                    @foreach ($paises as $codigo => $nombre)<option value="{{ $codigo }}" @selected($v('pais') === $codigo)>{{ $nombre }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                        @include('cuenta._campo', ['nombre' => 'telefono', 'etiqueta' => __('cuenta.telefono'), 'tipo' => 'tel', 'autocompletar' => 'tel', 'valor' => $v('telefono')])
                        <button type="submit" class="boton principal">{{ __('carrito.calcular_total') }}</button>
                    </form>
                @endif

                <aside class="carrito-resumen" id="total" aria-labelledby="titulo-resumen">
                    <h2 id="titulo-resumen">{{ __('carrito.resumen') }}</h2>
                    @if (session('aviso_pedido'))
                        <p class="aviso-cuenta error" role="alert">{{ session('aviso_pedido') }}</p>
                    @endif
                    <dl>
                        <div><dt>{{ __('carrito.prendas_impresion') }}</dt><dd>{{ Formato::euros($r->bruto()) }}</dd></div>
                        @if ($r->descuentos() > 0)
                            <div class="descuento"><dt>{{ $r->oferta['nombre'] ?? __('carrito.descuentos') }}{{ $r->cupon ? ' · '.$r->cupon['codigo'] : '' }}</dt><dd>−{{ Formato::euros($r->descuentos()) }}</dd></div>
                        @endif
                        @if ($r->envio)
                            <div><dt>{{ __('carrito.envio_metodo', ['zona' => $r->envio['zona'], 'plazo' => trans_choice('carrito.plazo_dias', $r->envio['plazo_dias'], ['n' => $r->envio['plazo_dias']])]) }}</dt><dd>{{ Formato::euros($r->envioImporte()) }}</dd></div>
                        @else
                            <div><dt>{{ __('carrito.envio') }}</dt><dd>{{ __('carrito.envio_al_cerrar') }}</dd></div>
                        @endif
                        <div><dt>{{ __('carrito.base') }}</dt><dd>{{ Formato::euros($r->baseImponible()) }}</dd></div>
                        <div><dt>{{ __('carrito.iva') }}</dt><dd>{{ Formato::euros($r->totalImpuestos() - $recargo) }}</dd></div>
                        @if ($recargo > 0)<div><dt>{{ __('carrito.recargo') }}</dt><dd>{{ Formato::euros($recargo) }}</dd></div>@endif
                    </dl>
                    <p class="cuenta-total"><span>{{ __('carrito.total') }}</span><strong>{{ Formato::euros($r->total()) }}</strong></p>
                    @if ($r->impedimentos || $valoracion->conProblemas())
                        <ul class="mensajes impedimentos">@foreach ($r->textos('impedimentos') as $i)<li>{{ $i }}</li>@endforeach @if ($valoracion->conProblemas())<li>{{ __('carrito.revisa_antes') }}</li>@endif</ul>
                    @elseif ($datos && ! $bloqueo && $valoracion->confirmable())
                        {{-- Confirmar (paso 6.1, D169): el servidor vuelve a valorar y solo crea el pedido con este mismo total. --}}
                        <form class="confirmar-pedido" method="POST" data-procesando="{{ __('tienda.procesando_pago') }}" data-procesando-detalle="{{ __('tienda.procesando_pago_detalle') }}" action="{{ CarritoController::url('confirmar') }}" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
                            @csrf
                            <input type="hidden" name="total" value="{{ $r->total() }}">
                            <label class="casilla-cuenta"><input type="checkbox" name="condiciones" value="1" required @checked(old('condiciones'))> <span>{{ __('pedidos.condiciones_acepto') }}</span></label>
                            <button type="submit" class="boton principal">{{ __('pedidos.confirmar_boton') }} <span class="sin-cortar">· {{ Formato::euros($r->total()) }}</span></button>
                            <p class="nota-cuenta">{{ $cliente ? __('pedidos.confirmar_nota_cuenta') : __('pedidos.confirmar_nota_invitado') }}</p>
                        </form>
                    @endif
                    <a class="boton-texto" href="{{ CarritoController::url('ver') }}">← {{ __('carrito.volver_carrito') }}</a>
                </aside>
            </div>
        </div>
    </section>
</x-tienda>
