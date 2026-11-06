# Restaurar una copia de seguridad

La restauración completa se hace a mano siguiendo estos pasos, no desde el backend (D26): sobrescribe todos los datos y debe ser una decisión consciente.

## Antes de empezar

- Las copias están en **Herramientas → Copias de seguridad**. Solo el Superadministrador puede descargarlas.
- En el servidor van cifradas: hace falta la contraseña `BACKUP_ARCHIVE_PASSWORD`, guardada fuera del servidor. **Sin ella no se pueden abrir.**
- Comprueba primero que la copia se puede restaurar: botón **Probar restauración**, o en la consola:

```bash
php artisan nga:copia-seguridad --probar
```

- Haz una copia del estado actual antes de restaurar (botón **Hacer copia ahora**), por si hay que volver atrás.

## Contenido del ZIP

| Ruta dentro del ZIP | Qué es |
|---|---|
| `copias-temp/base-de-datos.sqlite` | Base de datos completa (solo si se usa SQLite, en local) |
| `db-dumps/mariadb-<base>.sql` | Exportación de la base de datos (servidor, MariaDB) |
| `app/...` | Archivos subidos: diseños, PDF, imágenes (`storage/app`) |

El `.env` **no** se incluye (contiene contraseñas): guárdalo aparte.

## Local (Windows, SQLite)

1. Para `php artisan schedule:work` si está abierto.
2. Descomprime el ZIP en una carpeta temporal (con 7-Zip si va cifrado).
3. Sustituye la base de datos:

```bat
copy /Y RUTA\copias-temp\base-de-datos.sqlite %USERPROFILE%\nextgenapparel\database\database.sqlite
```

4. Copia la carpeta `app` del ZIP sobre `%USERPROFILE%\nextgenapparel\storage\app`.
5. Ejecuta `php artisan migrate` y `php artisan optimize:clear`, y entra en el backend para comprobarlo.

## Servidor (Docker, MariaDB)

Se completará en el paso 1.7 con los nombres de los contenedores. Esquema:

1. Activar el modo mantenimiento: `php artisan down`.
2. Descomprimir el ZIP con la contraseña de las copias.
3. Importar la base de datos: `mariadb -u USUARIO -p BASE < db-dumps/mariadb-BASE.sql`.
4. Copiar `app/` sobre `storage/app`.
5. `php artisan migrate --force`, `php artisan optimize:clear` y `php artisan up`.
6. Comprobar **Herramientas → Estado del sistema**.
