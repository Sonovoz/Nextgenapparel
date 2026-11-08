@php use App\Http\Controllers\PedidoController; @endphp
<x-tienda :titulo="__('pedidos.enlace_caducado_titulo').' · Nextgenapparel'" :descripcion="__('pedidos.enlace_caducado_titulo')" :alternos="$alternos" :seo="['noindex' => true]">
    <section class="carrito-pagina" aria-labelledby="titulo-caducado">
        <div class="contenedor">
            <h1 class="titulo-cuenta" id="titulo-caducado">{{ __('pedidos.enlace_caducado_titulo') }}</h1>
            <p class="entradilla">{{ __('pedidos.enlace_caducado_texto', ['numero' => $pedido->numero]) }}</p>
            @unless ($pedido->correoConfirmado())
                <form method="POST" action="{{ PedidoController::url('reenviar', $pedido) }}" style="margin-top: 24px;">@csrf <button type="submit" class="boton principal">{{ __('pedidos.reenviar_enlace') }}</button></form>
            @endunless
        </div>
    </section>
</x-tienda>
