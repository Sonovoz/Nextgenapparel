@props(['logo' => null])
@php
    // Datos de la tienda para la cabecera y el pie (D204); si la base de datos no responde, el correo sale igual.
    $tienda = rescue(fn () => \App\Models\Ajuste::obtener('tienda.nombre'), config('app.name'), report: false);
    $contacto = rescue(fn () => \App\Models\Ajuste::obtener('tienda.correo_contacto'), null, report: false);
    $idiomaCorreo = app()->getLocale();
    $legal = rescue(fn () => [
        route('legal', ['idioma' => $idiomaCorreo, 'pagina' => 'condiciones-de-venta']) => \App\Dominio\Legal\PaginaLegal::Condiciones->titulo(),
        route('legal', ['idioma' => $idiomaCorreo, 'pagina' => 'privacidad']) => \App\Dominio\Legal\PaginaLegal::Privacidad->titulo(),
        route('legal', ['idioma' => $idiomaCorreo, 'pagina' => 'aviso-legal']) => \App\Dominio\Legal\PaginaLegal::Aviso->titulo(),
    ], [], report: false);
@endphp
<x-mail::layout>
{{-- Cabecera: el logo de la marca --}}
<x-slot:header>
<x-mail::header :url="config('app.url')" :logo="$logo">
{{ $tienda }}
</x-mail::header>
</x-slot:header>

{{-- Cuerpo --}}
{!! $slot !!}

{{-- Subcopia --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Pie: la tienda, su web, el contacto y los textos legales --}}
<x-slot:footer>
<x-mail::footer>
**{{ $tienda }}** · {{ __('correos.pie_lema') }}

[{{ preg_replace('#^https?://#', '', rtrim((string) config('app.url'), '/')) }}]({{ config('app.url') }}){!! $contacto ? ' · ['.e($contacto).'](mailto:'.e($contacto).')' : '' !!}

@foreach ($legal as $url => $titulo)[{{ $titulo }}]({{ $url }})@if (! $loop->last) · @endif @endforeach
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
