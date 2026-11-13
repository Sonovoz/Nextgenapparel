{{-- D222: maqueta viva del pack. El JavaScript de la página la redibuja al cambiar de diseño o de color;
     sin JavaScript se ve con el diseño y los colores de partida. $donde: «paso» (móvil) o «resumen» (escritorio). --}}
@php
    $hayFotos = collect($lineas)->contains(fn ($l) => ($l['maqueta'][(int) old('elecciones.'.$l['linea']->id.'.color', $l['colores'][0]['id'] ?? array_key_first($l['maqueta']))] ?? []) !== []);
@endphp
<div @class(['pack-maqueta', 'pack-maqueta-'.$donde, 'con-diseno' => $disenoUrl, 'vacia' => ! $hayFotos]) data-maqueta>
    <p class="pack-maqueta-titulo">{{ __('packs.maqueta_titulo') }}</p>
    <div class="pack-maqueta-prendas" data-maqueta-prendas>
        @foreach ($lineas as $l)
            @php $color = (int) old('elecciones.'.$l['linea']->id.'.color', $l['colores'][0]['id'] ?? array_key_first($l['maqueta'])); @endphp
            @foreach ($l['maqueta'][$color] ?? [] as $cara)
                <figure class="pack-maqueta-cara">
                    <img src="{{ $cara['url'] }}" alt="{{ $l['linea']->prenda->nombre }}" width="500" height="600" loading="lazy">
                    @if ($disenoUrl)
                        @foreach ($cara['zonas'] as $z)
                            <span class="pack-maqueta-zona" style="left: {{ $z['x'] }}%; top: {{ $z['y'] }}%; width: {{ $z['ancho'] }}%; height: {{ $z['alto'] }}%;"><img src="{{ $disenoUrl }}" alt=""></span>
                        @endforeach
                    @endif
                </figure>
            @endforeach
        @endforeach
    </div>
    <p class="pack-nota" data-maqueta-nota>{{ $disenoUrl ? __('packs.maqueta_nota') : __('packs.maqueta_sin_diseno') }}</p>
</div>
