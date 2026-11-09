<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Pago simulado</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f4f6;font:16px system-ui,sans-serif;text-align:center}a{display:inline-block;margin:8px;padding:12px 24px;border-radius:8px;color:#fff;text-decoration:none}.ok{background:#1b7f4b}.ko{background:#b3261e}</style>
</head>
<body>
    <main>
        <h1>Pago simulado · {{ \App\Dominio\Precios\Formato::euros($importe, 'es') }}</h1>
        <p>Solo en pruebas: no se cobra nada.</p>
        <a class="ok" href="{{ $correcto }}">Pago correcto</a>
        <a class="ko" href="{{ $rechazado }}">Pago rechazado</a>
    </main>
</body>
</html>
