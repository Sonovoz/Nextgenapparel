<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>{{ __('pagos.redirigiendo') }}</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#07070C;color:#F3F2F8;font:16px system-ui,sans-serif;text-align:center}button{margin-top:16px;padding:12px 28px;border-radius:999px;border:1px solid #F3F2F8;background:none;color:inherit;font:inherit;cursor:pointer}</style>
</head>
<body>
    <form id="pago" method="{{ $iniciado->campos === [] ? 'GET' : 'POST' }}" action="{{ $iniciado->url }}">
        @foreach ($iniciado->campos as $nombre => $valor)
            <input type="hidden" name="{{ $nombre }}" value="{{ $valor }}">
        @endforeach
        <p>{{ __('pagos.redirigiendo') }}</p>
        <button type="submit">{{ __('pagos.continuar') }}</button>
    </form>
    <script>document.getElementById('pago').submit();</script>
</body>
</html>
