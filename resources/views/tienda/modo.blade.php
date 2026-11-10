@php $n = $modo->numero(); @endphp
<x-tienda :titulo="__('portada.quien_'.$n).' · Nextgenapparel'" :descripcion="__('portada.quien_'.$n.'_texto')" :alternos="$alternos">
    <section class="seccion modo-cabecera" aria-labelledby="titulo-modo">
        <div class="contenedor">
            <p class="etiqueta">{{ __('portada.modo') }} 0{{ $n }} · {{ __('portada.quien_'.$n) }}</p>
            <h1 class="titulo-seccion" id="titulo-modo">{{ __('portada.modo_packs_titulo') }}</h1>
            <p class="entradilla">{{ __('portada.quien_'.$n.'_texto') }}</p>

            @if ($packs->isEmpty())
                <p class="entradilla">{{ __('portada.modo_sin_packs') }}</p>
            @else
                <div class="carrusel">
                    @foreach ($packs as $pack)
                        @include('tienda._pack', ['pack' => $pack])
                    @endforeach
                </div>
            @endif

            <p class="entradilla" style="margin-top: 36px;">{{ __('portada.modo_a_medida') }}</p>
            <div class="modo-otros">
                <a class="boton principal" href="{{ route('portada') }}#pasos">{{ __('portada.cta_coleccion') }} <span aria-hidden="true">→</span></a>
                @foreach (\App\Dominio\Catalogo\ModoCliente::cases() as $otro)
                    @continue($otro === $modo)
                    <a class="boton" href="{{ route('modo', ['modo' => $otro->value]) }}">{{ __('portada.quien_'.$otro->numero()) }}</a>
                @endforeach
            </div>
        </div>
    </section>
</x-tienda>
