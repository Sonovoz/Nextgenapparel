# Instalación local en Windows con Herd

Entorno local del equipo: **Herd (gratuito)** para PHP y el servidor web, y **XAMPP solo para la base de datos** (MariaDB). En local las colas y la caché usan la base de datos y los correos se escriben en el log; nada sale a Internet.

## Requisitos

- **Herd para Windows**, con PHP 8.3 o superior activo (Herd → *PHP*). Herd ya incluye Composer.
- **XAMPP**: solo se usa el módulo **MySQL**. **No arranques Apache**: ocupa el puerto 80, el mismo que Herd.
- **Git para Windows**.

Todos los comandos se escriben en **PowerShell**.

## 1. Arrancar la base de datos

1. Abre el *XAMPP Control Panel*.
2. Pulsa **Start** solo en **MySQL**.
3. Crea la base de datos (una sola vez):

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS nextgenapparel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

> XAMPP trae el usuario `root` sin contraseña. Si le pusiste una, añade `-p` al comando y ponla también en `DB_PASSWORD` del `.env`.

## 2. Descargar el proyecto (una sola vez)

Herd sirve automáticamente las carpetas que hay dentro de `C:\Users\<tu usuario>\Herd`.

```powershell
cd $HOME\Herd
git clone https://github.com/Sonovoz/Nextgenapparel.git nextgenapparel
cd nextgenapparel
git checkout fase-1-base
```

## 3. Instalar y preparar

```powershell
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
```

## 4. Abrirlo en el navegador

- Portada: <http://nextgenapparel.test> (redirige a `/es`)
- En inglés: <http://nextgenapparel.test/en>

## Cada vez que haya un paso nuevo

```powershell
cd $HOME\Herd\nextgenapparel
git pull
composer install
php artisan migrate
```

## Si algo falla

| Síntoma | Solución |
|---|---|
| `nextgenapparel.test` no carga | Comprueba que Apache de XAMPP está **parado** y que Herd está abierto |
| `SQLSTATE[HY000] [2002]` al migrar | El módulo MySQL de XAMPP no está arrancado |
| `Unknown database 'nextgenapparel'` | Repite el paso 1.3 |
| Error de versión de PHP en `composer install` | En Herd → *PHP*, activa la 8.3 o superior |
| Página en blanco o error 500 | Revisa `storage\logs\laravel.log` |
