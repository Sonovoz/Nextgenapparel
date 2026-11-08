@php use App\Support\RutaCuenta; @endphp
<x-cuenta-marco :titulo="__('cuenta.restablecer_titulo')" :texto="__('cuenta.contrasena_ayuda')" :alternos="$alternos">
    <form method="POST" action="{{ RutaCuenta::url('restablecer.guardar') }}" class="formulario-cuenta" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        @include('cuenta._campo', ['nombre' => 'correo', 'etiqueta' => __('cuenta.correo'), 'tipo' => 'email', 'autocompletar' => 'email', 'valor' => $correo])
        @include('cuenta._campo', ['nombre' => 'contrasena', 'etiqueta' => __('cuenta.contrasena'), 'tipo' => 'password', 'autocompletar' => 'new-password'])
        @include('cuenta._campo', ['nombre' => 'contrasena_confirmation', 'etiqueta' => __('cuenta.contrasena_repetir'), 'tipo' => 'password', 'autocompletar' => 'new-password'])
        <button type="submit" class="boton principal">{{ __('cuenta.guardar_contrasena') }}</button>
    </form>
</x-cuenta-marco>
