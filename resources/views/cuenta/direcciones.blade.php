@php use App\Support\RutaCuenta; use App\Dominio\Clientes\TipoDireccion; use App\Dominio\Clientes\Paises; @endphp
<x-cuenta-area :titulo="__('cuenta.direcciones_titulo')" :texto="__('cuenta.direcciones_texto')" :alternos="$alternos" seccion="direcciones">
    @foreach (TipoDireccion::cases() as $tipo)
        <section class="grupo-direcciones" aria-labelledby="dir-{{ $tipo->value }}">
            <h2 class="subtitulo-cuenta" id="dir-{{ $tipo->value }}">{{ $tipo->getLabel() }}</h2>
            @forelse ($direcciones[$tipo->value] ?? [] as $d)
                <article class="direccion-tarjeta">
                    @if ($d->predeterminada)<p class="insignia">{{ __('cuenta.predeterminada') }}</p>@endif
                    <address>
                        <strong>{{ $d->nombre }}</strong>@if ($d->empresa) · {{ $d->empresa }}@endif<br>
                        {{ $d->direccion }}@if ($d->direccion_2), {{ $d->direccion_2 }}@endif<br>
                        {{ $d->codigo_postal }} {{ $d->localidad }}@if ($d->provincia) ({{ $d->provincia }})@endif · {{ Paises::nombre($d->pais) }}
                        @if ($d->telefono)<br>{{ $d->telefono }}@endif
                    </address>
                    <div class="direccion-acciones">
                        <a class="boton-texto" href="{{ RutaCuenta::url('direcciones.editar', ['direccion' => $d->uuid]) }}">{{ __('cuenta.editar') }}<span class="oculto"> · {{ $d->direccion }}</span></a>
                        @unless ($d->predeterminada)
                            <form method="POST" action="{{ RutaCuenta::url('direcciones.predeterminar', ['direccion' => $d->uuid]) }}">@csrf<button type="submit" class="boton-texto">{{ __('cuenta.hacer_predeterminada') }}<span class="oculto"> · {{ $d->direccion }}</span></button></form>
                        @endunless
                        <form method="POST" action="{{ RutaCuenta::url('direcciones.borrar', ['direccion' => $d->uuid]) }}" onsubmit="return confirm(@js(__('cuenta.borrar_direccion_confirmar')))">@csrf @method('DELETE')<button type="submit" class="boton-texto peligro">{{ __('cuenta.borrar') }}<span class="oculto"> · {{ $d->direccion }}</span></button></form>
                    </div>
                </article>
            @empty
                <p class="claro">{{ __('cuenta.sin_direcciones') }}</p>
            @endforelse
            <a class="boton" href="{{ RutaCuenta::url('direcciones.crear') }}?tipo={{ $tipo->value }}">+ {{ __('cuenta.nueva_direccion') }}</a>
        </section>
    @endforeach
</x-cuenta-area>
