@php use App\Support\RutaCuenta; @endphp
<x-cuenta-area :titulo="__('cuenta.contrasena_titulo')" :texto="__('cuenta.contrasena_ayuda')" :alternos="$alternos" seccion="contrasena">
    <form method="POST" action="{{ RutaCuenta::url('contrasena.guardar') }}" class="formulario-cuenta" novalidate>
        @csrf @method('PUT')
        @include('cuenta._campo', ['nombre' => 'contrasena_actual', 'etiqueta' => __('cuenta.contrasena_actual'), 'tipo' => 'password', 'autocompletar' => 'current-password'])
        @include('cuenta._campo', ['nombre' => 'contrasena', 'etiqueta' => __('cuenta.contrasena_nueva'), 'tipo' => 'password', 'autocompletar' => 'new-password', 'ayuda' => __('cuenta.contrasena_ayuda')])
        @include('cuenta._campo', ['nombre' => 'contrasena_confirmation', 'etiqueta' => __('cuenta.contrasena_repetir'), 'tipo' => 'password', 'autocompletar' => 'new-password'])
        <button type="submit" class="boton principal">{{ __('cuenta.guardar') }}</button>
    </form>
</x-cuenta-area>
