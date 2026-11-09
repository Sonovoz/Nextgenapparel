<x-tienda :titulo="__('blog.blog').' · Nextgenapparel'" :descripcion="__('blog.descripcion')" :alternos="$alternos">
    <section class="carrito-pagina">
        <div class="contenedor">
            <h1>{{ __('blog.blog') }}</h1>
            @forelse ($entradas as $entrada)
                <article class="entrada-blog">
                    <h2><a href="{{ route('blog.ver', ['slug' => \App\Dominio\Catalogo\Escaparate::slug($entrada, app()->getLocale())]) }}">{{ $entrada->titulo }}</a></h2>
                    <p class="claro"><small>{{ $entrada->publicada_en->locale(app()->getLocale())->isoFormat('LL') }}</small></p>
                    @if ($entrada->extracto)
                        <p>{{ $entrada->extracto }}</p>
                    @endif
                </article>
            @empty
                <p>{{ __('blog.vacio') }}</p>
            @endforelse
            {{ $entradas->links() }}
        </div>
    </section>
</x-tienda>
