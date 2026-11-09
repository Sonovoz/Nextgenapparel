<x-tienda :titulo="$texto->pagina->titulo().' · Nextgenapparel'" :descripcion="$texto->pagina->titulo()" :alternos="$alternos">
    <section class="carrito-pagina">
        <div class="contenedor">
            <article class="texto-legal">
                @if (__('legal.solo_es') !== '')
                    <p class="aviso-cuenta verificar" lang="en">{{ __('legal.solo_es') }}</p>
                @endif
                @unless ($texto->revisado_en)
                    <p class="aviso-cuenta verificar">{{ __('legal.borrador') }}</p>
                @endunless
                <div lang="es">{!! $html !!}</div>
                <p class="claro"><small>{{ __('legal.version', ['n' => $texto->version, 'fecha' => $texto->generado_en->locale(app()->getLocale())->isoFormat('LL')]) }}</small></p>
            </article>
        </div>
    </section>
</x-tienda>
