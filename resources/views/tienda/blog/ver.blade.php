<x-tienda :titulo="($entrada->seo_titulo ?: $entrada->titulo).' · Nextgenapparel'" :descripcion="$entrada->seo_descripcion ?: $entrada->extracto" :alternos="$alternos">
    <section class="carrito-pagina">
        <div class="contenedor">
            <article class="texto-legal">
                <p><a href="{{ route('blog') }}">← {{ __('blog.blog') }}</a></p>
                <h1>{{ $entrada->titulo }}</h1>
                <p class="claro"><small>{{ $entrada->publicada_en->locale(app()->getLocale())->isoFormat('LL') }}</small></p>
                <div>{!! $html !!}</div>
            </article>
        </div>
    </section>
</x-tienda>
