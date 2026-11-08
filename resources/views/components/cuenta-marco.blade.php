@props(['titulo', 'texto', 'alternos', 'clase' => ''])
{{-- Marco de las páginas de la cuenta (paso 5.2): tarjeta centrada, avisos y errores. Siempre noindex. --}}
<x-tienda :titulo="$titulo.' · Nextgenapparel'" :descripcion="$texto" :alternos="$alternos" :seo="['noindex' => true]">
    <section class="cuenta-pagina {{ $clase }}" aria-labelledby="titulo-cuenta">
        <div class="contenedor">
            <div class="tarjeta-cuenta">
                <h1 class="titulo-cuenta" id="titulo-cuenta">{{ $titulo }}</h1>
                <p class="entradilla">{{ $texto }}</p>
                @if (session('estado'))
                    <p class="aviso-cuenta correcto" role="status">{{ session('estado') }}</p>
                @endif
                @if (session('error'))
                    <p class="aviso-cuenta error" role="alert">{{ session('error') }}</p>
                @endif
                {{ $slot }}
            </div>
        </div>
    </section>
</x-tienda>
