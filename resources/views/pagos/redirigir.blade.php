<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>{{ __('pagos.redirigiendo') }}</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:radial-gradient(60% 50% at 50% 45%,rgba(45,75,255,.18),transparent 70%),#07070C;color:#F3F2F8;font:16px system-ui,sans-serif;text-align:center}button{margin-top:16px;padding:12px 28px;border-radius:999px;border:1px solid #F3F2F8;background:none;color:inherit;font:inherit;cursor:pointer}
.orbita{display:block;width:76px;height:76px;margin:0 auto 22px;border-radius:50%;background:conic-gradient(from 0deg,transparent 0 55%,rgba(142,160,255,.35) 70%,#8EA0FF 88%,#fff 100%);-webkit-mask:radial-gradient(farthest-side,transparent calc(100% - 3px),#000 calc(100% - 2px));mask:radial-gradient(farthest-side,transparent calc(100% - 3px),#000 calc(100% - 2px));animation:giro 1.1s linear infinite;filter:drop-shadow(0 0 10px rgba(142,160,255,.7))}
.titulo{margin:0;font-size:14px;font-weight:600;letter-spacing:.28em;text-transform:uppercase}.detalle{margin:10px 0 0;color:#C8CCE4;font-size:14px}@keyframes giro{to{transform:rotate(1turn)}}</style>
</head>
<body>
    @if ($iniciado->campos === [])
        <span class="orbita" aria-hidden="true"></span>
        <p class="titulo" role="status">{{ __('tienda.procesando_pago') }}</p>
        <p class="detalle">{{ __('tienda.procesando_pago_detalle') }}</p>
        <a href="{{ $iniciado->url }}">{{ __('pagos.continuar') }}</a>
        <script>window.location.replace(@js($iniciado->url));</script>
    @else
    <form id="pago" method="POST" action="{{ $iniciado->url }}">
        @foreach ($iniciado->campos as $nombre => $valor)
            <input type="hidden" name="{{ $nombre }}" value="{{ $valor }}">
        @endforeach
        <span class="orbita" aria-hidden="true"></span>
        <p class="titulo" role="status">{{ __('tienda.procesando_pago') }}</p>
        <p class="detalle">{{ __('tienda.procesando_pago_detalle') }}</p>
        <button type="submit">{{ __('pagos.continuar') }}</button>
    </form>
    <script>document.getElementById('pago').submit();</script>
    @endif
</body>
</html>
