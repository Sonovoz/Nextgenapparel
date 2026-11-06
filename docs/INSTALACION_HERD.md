# Instalación local en Windows con Herd

Entorno local del equipo: **Herd (gratuito)** para PHP y el servidor web, y **XAMPP solo para la base de datos** (MariaDB). En local las colas y la caché usan la base de datos y los correos se escriben en el log; nada sale a Internet.

## Requisitos

- **Herd para Windows**, con PHP 8.3 o superior activo (Herd → *PHP*). Herd ya incluye Composer.
- **XAMPP**: solo se usa el módulo **MySQL**. **No arranques Apache**: ocupa el puerto 80, el mismo que Herd.
- **Git para Windows**.

Los comandos sirven tanto en **Símbolo del sistema (cmd)** como en **PowerShell**, salvo donde se indica.

## 1. Arrancar la base de datos

1. Abre el *XAMPP Control Panel*.
2. Pulsa **Start** solo en **MySQL**.
3. Crea la base de datos (una sola vez):

```bat
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS nextgenapparel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

> XAMPP trae el usuario `root` sin contraseña. Si le pusiste una, añade `-p` al comando y ponla también en `DB_PASSWORD` del `.env`.

## 2. Descargar el proyecto (una sola vez)

Puedes clonarlo en la carpeta que quieras, por ejemplo en tu carpeta de usuario:

```bat
cd %USERPROFILE%
git clone -b fase-1-base https://github.com/Sonovoz/Nextgenapparel.git nextgenapparel
cd nextgenapparel
```

> En PowerShell, usa `cd $HOME` en lugar de `cd %USERPROFILE%`.

## 3. Instalar y preparar

```bat
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
```

## 4. Enlazarlo con Herd (una sola vez)

Herd solo sirve automáticamente las carpetas que están dentro de `C:\Users\<tu usuario>\Herd`. Si el proyecto está en otra carpeta, enlázalo desde la carpeta del proyecto:

```bat
herd link nextgenapparel
```

Si el comando `herd` no se reconoce: Herd → **Sites** → **Add** (enlazar un proyecto existente), elige la carpeta del proyecto y ponle de nombre `nextgenapparel`.

## 5. Abrirlo en el navegador

- Portada: <http://nextgenapparel.test> (redirige a `/es`)
- En inglés: <http://nextgenapparel.test/en>

## 6. Entrar en el backend

Crea tu usuario administrador (una sola vez). Te pedirá nombre, correo y una contraseña de al menos 12 caracteres:

```bat
php artisan nga:crear-administrador
```

Después entra en <http://nextgenapparel.test/admin> con ese correo y contraseña.

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
| `nextgenapparel.test` no carga | Comprueba que el proyecto está enlazado (paso 4), que Apache de XAMPP está **parado** y que Herd está abierto |
| «El sistema no puede encontrar la ruta especificada» con `$HOME` | Estás en cmd: usa `%USERPROFILE%` (o abre PowerShell) |
| `SQLSTATE[HY000] [2002]` al migrar | El módulo MySQL de XAMPP no está arrancado |
| `Unknown database 'nextgenapparel'` | Repite el punto 3 del paso 1 |
| Error de versión de PHP en `composer install` | En Herd → *PHP*, activa la 8.3 o superior |
| Página en blanco o error 500 | Revisa `storage\logs\laravel.log` |
| `composer install` pide la extensión `bcmath` | En Herd → *PHP* → *Extensions*, activa `bcmath` |
| El backend se ve sin estilos | Ejecuta `php artisan filament:upgrade` y recarga con Ctrl + F5 |
