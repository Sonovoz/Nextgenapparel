<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>{{ __('marketing.baja_titulo') }}</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#07070C;color:#F3F2F8;font:16px system-ui,sans-serif;text-align:center;padding:16px}a{color:#8EA0FF}</style>
</head>
<body>
    <main>
        <h1>{{ __('marketing.baja_titulo') }}</h1>
        <p>{{ __('marketing.baja_hecha') }}</p>
        <p><a href="{{ url('/'.app()->getLocale()) }}">Nextgenapparel</a></p>
    </main>
</body>
</html>
