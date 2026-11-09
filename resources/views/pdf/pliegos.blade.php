<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; }
    body { margin: 0; }
    .pliego { position: relative; page-break-after: always; }
    .pliego:last-child { page-break-after: auto; }
    .pieza { position: absolute; }
</style>
</head>
<body>
@forelse ($paginas as $pagina)
    <div class="pliego" style="width: {{ $pagina['ancho'] }}mm; height: {{ $pagina['largo'] }}mm;">
        @foreach ($pagina['piezas'] as $p)
            <img class="pieza" src="{{ $p['ruta'] }}" style="left: {{ $p['x'] }}mm; top: {{ $p['y'] }}mm; width: {{ $p['ancho'] }}mm; height: {{ $p['alto'] }}mm;" alt="">
        @endforeach
    </div>
@empty
    <p style="font-family: sans-serif; padding: 20mm;">No hay diseños de pedidos en producción.</p>
@endforelse
</body>
</html>
