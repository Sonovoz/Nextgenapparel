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

Ejemplo para producción (para pruebas, cambia `nga-produccion` por `nga-pruebas`).

1. Descarga la copia desde el backend o cópiala del volumen y descomprímela en `/tmp/restaurar` con la contraseña de las copias (`7z x copia.zip -o/tmp/restaurar`).
2. Para la aplicación sin parar la base de datos:

```bash
docker compose -p nga-produccion exec app php artisan down
docker compose -p nga-produccion stop cola tareas
```

3. Importa la base de datos (usuario y contraseña del `.env` del entorno):

```bash
docker compose -p nga-produccion exec -T mariadb mariadb -u nextgenapparel -p nextgenapparel < /tmp/restaurar/db-dumps/mariadb-nextgenapparel.sql
```

4. Restaura los archivos:

```bash
docker compose -p nga-produccion cp /tmp/restaurar/app/. app:/var/www/html/storage/app/
```

5. Vuelve a ponerla en marcha y comprueba:

```bash
docker compose -p nga-produccion exec app php artisan migrate --force
docker compose -p nga-produccion exec app php artisan optimize:clear
docker compose -p nga-produccion start cola tareas
docker compose -p nga-produccion exec app php artisan up
docker compose -p nga-produccion exec app php artisan nga:comprobar-salud --sin-avisos
```

6. Borra `/tmp/restaurar`: contiene datos personales.
