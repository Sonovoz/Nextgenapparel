@php use App\Support\RutaCuenta; @endphp
<x-cuenta-area :titulo="__('cuenta.hola', ['nombre' => $usuario->name])" :texto="__('cuenta.cuenta_texto')" :alternos="$alternos" seccion="inicio">
    @unless ($usuario->hasVerifiedEmail())
        <div class="aviso-cuenta verificar" role="status">
            <strong>{{ __('cuenta.verifica_titulo') }}</strong>
            <p>{{ __('cuenta.verifica_texto', ['correo' => $usuario->email]) }}</p>
            <form method="POST" action="{{ RutaCuenta::url('reenviar') }}">@csrf<button type="submit" class="boton">{{ __('cuenta.verifica_reenviar') }}</button></form>
        </div>
    @endunless

    <h2 class="subtitulo-cuenta">{{ __('cuenta.mis_pedidos') }}</h2>
    <p class="claro">{{ __('cuenta.sin_pedidos') }}</p>

    <h2 class="subtitulo-cuenta">{{ __('cuenta.mis_disenos') }}</h2>
    @if ($disenos->isEmpty())
        <p class="claro">{{ __('cuenta.sin_disenos') }} <a href="{{ route('portada') }}#prendas">{{ __('cuenta.ver_prendas') }}</a></p>
    @else
        <ul class="mis-disenos">
            @foreach ($disenos->take(6) as $d)
                <li><img src="{{ $d['miniatura'] }}" alt="" loading="lazy" width="96" height="96"><span>{{ $d['nombre'] }}</span><small>{{ $d['ancho_px'] }} × {{ $d['alto_px'] }} px</small></li>
            @endforeach
        </ul>
        <p><a class="enlace-area" href="{{ RutaCuenta::url('disenos') }}">{{ __('cuenta.nav_disenos') }} →</a></p>
    @endif
</x-cuenta-area>
