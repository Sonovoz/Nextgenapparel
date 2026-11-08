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
                @if ($errors->any())
                    {{-- Resumen al principio y con el foco: tras enviar, el lector de pantalla y el teclado empiezan aquí (WCAG 3.3.1). --}}
                    <div class="aviso-cuenta error resumen-errores" role="alert" tabindex="-1" id="resumen-errores">
                        <strong>{{ trans_choice('cuenta.revisa_errores', $errors->count(), ['n' => $errors->count()]) }}</strong>
                        <ul>@foreach ($errors->keys() as $campo)<li><a href="#{{ $campo === 'tipo' ? 'grupo-tipo' : 'campo-'.$campo }}">{{ $errors->first($campo) }}</a></li>@endforeach</ul>
                    </div>
                    <script>document.getElementById('resumen-errores').focus();</script>
                @endif
                {{ $slot }}
            </div>
        </div>
    </section>
</x-tienda>
