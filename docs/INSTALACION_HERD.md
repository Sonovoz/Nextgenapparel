# Instalación local en Windows con Herd

Entorno local del equipo: **Herd (gratuito)** para PHP y el servidor web, y **SQLite** como base de datos: un único archivo dentro del proyecto (`database\database.sqlite`), sin instalar ni arrancar ningún servidor. En el servidor de OVH se usa MariaDB. En local las colas y la caché usan la base de datos y los correos se escriben en el log; nada sale a Internet.

## Requisitos

- **Herd para Windows**, con PHP 8.3 o superior activo (Herd → *PHP*). Herd ya incluye Composer.
- **No hace falta XAMPP.** Si lo tienes, deja **Apache parado**: ocupa el puerto 80, el mismo que Herd.
- **Git para Windows**.

Los comandos sirven tanto en **Símbolo del sistema (cmd)** como en **PowerShell**, salvo donde se indica.

## 1. Descargar el proyecto (una sola vez)

Puedes clonarlo en la carpeta que quieras, por ejemplo en tu carpeta de usuario:

```bat
cd %USERPROFILE%
git clone -b fase-1-base https://github.com/Sonovoz/Nextgenapparel.git nextgenapparel
cd nextgenapparel
```

> En PowerShell, usa `cd $HOME` en lugar de `cd %USERPROFILE%`.

## 2. Instalar y preparar

```bat
composer install
copy .env.example .env
php artisan key:generate
type nul > database\database.sqlite
php artisan migrate
```

> `type nul > database\database.sqlite` crea el archivo vacío de la base de datos (en PowerShell: `New-Item database\database.sqlite`).

## 3. Enlazarlo con Herd (una sola vez)

Herd solo sirve automáticamente las carpetas que están dentro de `C:\Users\<tu usuario>\Herd`. Si el proyecto está en otra carpeta, enlázalo desde la carpeta del proyecto:

```bat
herd link nextgenapparel
```

Si el comando `herd` no se reconoce: Herd → **Sites** → **Add** (enlazar un proyecto existente), elige la carpeta del proyecto y ponle de nombre `nextgenapparel`.

## 4. Abrirlo en el navegador

- Portada: <http://nextgenapparel.test> (redirige a `/es`)
- En inglés: <http://nextgenapparel.test/en>

## 5. Entrar en el backend

Crea tu usuario administrador (una sola vez). Te pedirá nombre, correo y una contraseña de al menos 12 caracteres:

```bat
php artisan nga:crear-administrador
```

Después entra en <http://nextgenapparel.test/admin> con ese correo y contraseña.

El comando crea un **Superadministrador**. El resto de usuarios se crean desde el backend, en **Usuarios y roles → Usuarios**. También se puede indicar el rol en el comando con `--rol=administrador`, `--rol=gestor-tienda` o `--rol=produccion`.

## 6. Alertas de errores (opcional en local)

Para probar las alertas de errores críticos, añade al final de tu `.env`:

```
ALERTAS_EMAIL=asanchezdelolmo1971@gmail.com
```

En local el correo **no se envía**: se escribe al final de `storage\logs\laravel.log`. Para provocar un error de prueba: en el backend, **Herramientas → Log de errores → Provocar error de prueba**, o en la consola:

```bat
php artisan nga:error-prueba
```

## Cada vez que haya un paso nuevo

```bat
cd %USERPROFILE%\nextgenapparel
git pull
composer install
php artisan migrate
```

## Si algo falla

| Síntoma | Solución |
|---|---|
| `nextgenapparel.test` no carga | Comprueba que el proyecto está enlazado (paso 3), que Herd está abierto y que, si tienes XAMPP, su Apache está **parado** |
| «El sistema no puede encontrar la ruta especificada» con `$HOME` | Estás en cmd: usa `%USERPROFILE%` (o abre PowerShell) |
| `Base table or view not found` / `doesn't exist` | Falta ejecutar `php artisan migrate` tras el `git pull` |
| `Database file at path [...] does not exist` | Crea el archivo: `type nul > database\database.sqlite` |
| `could not find driver` al migrar | En Herd → *PHP* → *Extensions*, activa `pdo_sqlite` |
| Error de versión de PHP en `composer install` | En Herd → *PHP*, activa la 8.3 o superior |
| Página en blanco o error 500 | Revisa `storage\logs\laravel.log` |
| `composer install` pide la extensión `bcmath` | En Herd → *PHP* → *Extensions*, activa `bcmath` |
| El backend se ve sin estilos | Ejecuta `php artisan filament:upgrade` y recarga con Ctrl + F5 |
