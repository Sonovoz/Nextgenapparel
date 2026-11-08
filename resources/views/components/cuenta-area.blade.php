@props(['titulo', 'texto' => null, 'alternos', 'seccion'])
@php
    use App\Support\RutaCuenta;
    $secciones = ['inicio' => 'nav_resumen', 'datos' => 'nav_datos', 'direcciones' => 'nav_direcciones', 'disenos' => 'nav_disenos', 'contrasena' => 'nav_contrasena'];
@endphp
{{-- Área de cliente (paso 5.3): navegación por secciones y contenido. Siempre noindex. --}}
<x-tienda :titulo="$titulo.' · '.__('cuenta.area').' · Nextgenapparel'" :descripcion="$texto ?? __('cuenta.cuenta_texto')" :alternos="$alternos" :seo="['noindex' => true]">
    <section class="cuenta-pagina area-cliente" aria-labelledby="titulo-cuenta">
        <div class="contenedor area-rejilla">
            <nav class="area-nav" aria-label="{{ __('cuenta.area') }}">
                <p class="etiqueta">{{ __('cuenta.area') }}</p>
                <ul>
                    @foreach ($secciones as $clave => $texto_nav)
                        <li><a href="{{ RutaCuenta::url($clave) }}" @if ($seccion === $clave) aria-current="page" @endif>{{ __('cuenta.'.$texto_nav) }}</a></li>
                    @endforeach
                </ul>
                <form method="POST" action="{{ RutaCuenta::url('salir') }}">@csrf<button type="submit" class="area-salir">{{ __('cuenta.salir') }}</button></form>
            </nav>
            <div class="tarjeta-cuenta area-contenido">
                <h1 class="titulo-cuenta" id="titulo-cuenta">{{ $titulo }}</h1>
                @if ($texto)<p class="entradilla">{{ $texto }}</p>@endif
                @if (session('estado'))
                    <p class="aviso-cuenta correcto" role="status">{{ session('estado') }}</p>
                @endif
                @if (session('error'))
                    <p class="aviso-cuenta error" role="alert">{{ session('error') }}</p>
                @endif
                @if ($errors->any())
                    <div class="aviso-cuenta error resumen-errores" role="alert" tabindex="-1" id="resumen-errores">
                        <strong>{{ trans_choice('cuenta.revisa_errores', $errors->count(), ['n' => $errors->count()]) }}</strong>
                        <ul>@foreach ($errors->keys() as $campo)<li><a href="#campo-{{ $campo }}">{{ $errors->first($campo) }}</a></li>@endforeach</ul>
                    </div>
                    <script>document.getElementById('resumen-errores').focus();</script>
                @endif
                {{ $slot }}
            </div>
        </div>
    </section>
</x-tienda>
