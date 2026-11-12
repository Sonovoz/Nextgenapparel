<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Primera instalación · Nextgenapparel</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px;
               background: #fafafa; color: #18181b; font: 15px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
        .tarjeta { width: 100%; max-width: 460px; background: #fff; border: 1px solid #e4e4e7; border-radius: 12px; padding: 32px; }
        h1 { margin: 0 0 4px; font-size: 22px; text-align: center; }
        .marca { margin: 0 0 20px; text-align: center; font-weight: 600; color: #52525b; }
        .paso { background: #f4f4f5; border-radius: 8px; padding: 12px 14px; font-size: 14px; margin-bottom: 20px; }
        .paso code { display: block; margin-top: 6px; word-break: break-all; font-size: 13px; color: #27272a; }
        label { display: block; font-weight: 500; margin: 14px 0 6px; }
        input { width: 100%; padding: 9px 11px; border: 1px solid #d4d4d8; border-radius: 8px; font: inherit; }
        input:focus { outline: 2px solid #52525b; outline-offset: 0; border-color: #52525b; }
        .error { color: #b91c1c; font-size: 13px; margin-top: 4px; }
        .ayuda { color: #71717a; font-size: 13px; margin-top: 4px; }
        button { width: 100%; margin-top: 22px; padding: 10px; border: 0; border-radius: 8px; background: #3f3f46; color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
        button:hover { background: #27272a; }
    </style>
</head>
<body>
<main class="tarjeta">
    <p class="marca">Nextgenapparel</p>
    <h1>Primera instalación</h1>

    <div class="paso">
        <strong>1.</strong> Abre con filebrowser este archivo del servidor y copia la clave que pone en «CLAVE»:
        <code>{{ $archivo }}</code>
        <strong>2.</strong> Crea aquí tu usuario del backend. Al crearlo, la clave y el archivo se borran solos.
    </div>

    <form method="POST" action="{{ route('instalacion') }}">
        @csrf

        <label for="clave">Clave temporal</label>
        <input id="clave" name="clave" type="text" required autocomplete="off" autofocus>
        @error('clave') <p class="error">{{ $message }}</p> @enderror

        <label for="nombre">Tu nombre</label>
        <input id="nombre" name="nombre" type="text" required value="{{ old('nombre') }}" autocomplete="name">
        @error('nombre') <p class="error">{{ $message }}</p> @enderror

        <label for="email">Correo electrónico</label>
        <input id="email" name="email" type="email" required value="{{ old('email') }}" autocomplete="email">
        @error('email') <p class="error">{{ $message }}</p> @enderror

        <label for="password">Contraseña</label>
        <input id="password" name="password" type="password" required autocomplete="new-password">
        <p class="ayuda">Mínimo 12 caracteres. Se guarda cifrada.</p>
        @error('password') <p class="error">{{ $message }}</p> @enderror

        <label for="password_confirmation">Repite la contraseña</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">

        <button type="submit">Crear mi usuario y entrar</button>
    </form>
</main>
</body>
</html>
