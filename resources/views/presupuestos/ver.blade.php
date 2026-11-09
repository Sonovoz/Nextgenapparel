@php use App\Dominio\Precios\Formato; use App\Http\Controllers\PresupuestoController; $c = $presupuesto->contenido; @endphp
<x-tienda :titulo="__('presupuestos.titulo', ['numero' => $presupuesto->numero]).' · Nextgenapparel'" :descripcion="__('presupuestos.titulo', ['numero' => $presupuesto->numero])" :alternos="$alternos" :seo="['noindex' => true]">
    <section class="carrito-pagina">
        <div class="contenedor">
            <h1 class="titulo-cuenta">{{ __('presupuestos.titulo', ['numero' => $presupuesto->numero]) }}</h1>
            <p class="entradilla">{{ $presupuesto->vigente() ? __('presupuestos.valido', ['fecha' => $presupuesto->valido_hasta->locale(app()->getLocale())->isoFormat('LL')]) : __('presupuestos.caducado') }}</p>
            @if (session('estado'))<p class="aviso-cuenta verificar" role="status">{{ session('estado') }}</p>@endif
            <div class="pedido-tarjeta">
                <ul class="pedido-impresiones">
                    @foreach ($c['lineas'] as $l)
                        <li><strong>{{ $l['descripcion'] }}</strong> <span>{{ $l['detalle'] }}</span> <small>{{ Formato::euros($l['neto']) }} {{ __('pedidos.sin_iva') }}</small></li>
                    @endforeach
                </ul>
                <p class="cuenta-total"><span>{{ __('carrito.total') }}</span><strong>{{ Formato::euros($c['total']) }}</strong></p>
                <p class="claro">{{ __('presupuestos.sin_envio') }}</p>
            </div>
            <div class="botones-formulario" style="margin-top: 20px;">
                <a class="boton" href="{{ PresupuestoController::url('pdf', $presupuesto) }}">{{ __('presupuestos.descargar') }}</a>
                @if ($presupuesto->vigente())
                    <form method="POST" action="{{ PresupuestoController::url('pasar', $presupuesto) }}">@csrf<button type="submit" class="boton principal">{{ __('presupuestos.pasar') }}</button></form>
                @endif
            </div>
        </div>
    </section>
</x-tienda>
