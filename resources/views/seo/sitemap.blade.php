{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
@foreach ($urls as $url)
    <url>
        <loc>{{ $url['loc'] }}</loc>
@foreach ($url['alternos'] as $codigo => $alterno)
        <xhtml:link rel="alternate" hreflang="{{ $codigo }}" href="{{ $alterno }}"/>
@endforeach
@if ($url['lastmod'])
        <lastmod>{{ $url['lastmod'] }}</lastmod>
@endif
@if ($url['imagen'])
        <image:image><image:loc>{{ $url['imagen'] }}</image:loc></image:image>
@endif
    </url>
@endforeach
</urlset>
