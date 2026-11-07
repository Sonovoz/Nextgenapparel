# Despliegue en el servidor dedicado de OVH

Pruebas y producción viven en el mismo servidor, cada uno con sus contenedores, su base de datos, sus archivos y su `.env` (D25). Delante de los dos está **Caddy**, que recibe el tráfico de los dominios y renueva los certificados SSL solo.

```
Internet ──► Caddy (80/443) ──► nga-produccion: web ► app · cola · tareas ► MariaDB · Redis
                            └──► nga-pruebas:    web ► app · cola · tareas ► MariaDB · Redis · Mailpit
```

| Contenedor | Qué hace |
|---|---|
| `web` | Nginx: sirve la web y pasa PHP a `app` |
| `app` | PHP-FPM. Al arrancar aplica las migraciones |
| `cola` | Procesa los trabajos en segundo plano (correos, PDF…) |
| `tareas` | Tareas programadas: copias, comprobación de salud, limpiezas |
| `mariadb` | Base de datos |
| `redis` | Caché, sesiones y cola |
| `mailpit` | Solo en pruebas: atrapa los correos para que no lleguen a nadie |

Todos se reinician solos si se caen y tienen comprobación de salud.

## 1. Preparar el servidor (una sola vez)

1. Instala Docker con el plugin de Compose (guía oficial de Docker para Debian o Ubuntu).
2. Abre en el cortafuegos solo los puertos 22, 80 y 443.
3. Descarga el proyecto:

```bash
git clone https://github.com/Sonovoz/Nextgenapparel.git /srv/nextgenapparel
cd /srv/nextgenapparel
```

4. Apunta los dominios (registros DNS `A`) a la IP del servidor.

## 2. Configurar los entornos (una sola vez)

```bash
cp docker/env/produccion.env.example docker/env/produccion.env
cp docker/env/pruebas.env.example docker/env/pruebas.env
cp docker/caddy/.env.example docker/caddy/.env
```

Rellena en cada `docker/env/*.env`:

- `APP_KEY`: genérala con `docker run --rm nextgenapparel/app:local php artisan key:generate --show` tras el primer despliegue, o con `openssl rand -base64 32` precedido de `base64:`.
- `APP_URL`, `DB_PASSWORD` (una distinta por entorno), `ALERTAS_EMAIL`.
- Producción: los datos SMTP del proveedor de correo.
- `BACKUP_ARCHIVE_PASSWORD`: **guárdala también fuera del servidor**. Sin ella no se pueden abrir las copias.

En `docker/caddy/.env`: los dos dominios y el correo para Let's Encrypt.

Estos archivos no se suben nunca al repositorio.

## 3. Arrancar

```bash
docker compose -f docker/caddy/compose.yaml --env-file docker/caddy/.env up -d
./docker/desplegar.sh pruebas
./docker/desplegar.sh produccion
```

`desplegar.sh` construye la imagen, arranca los contenedores y espera a que todos estén sanos. Crea el primer administrador:

```bash
docker compose -p nga-produccion exec app php artisan nga:crear-administrador
```

## 4. Actualizar a una versión nueva

Primero en pruebas y, si todo va bien, en producción:

```bash
git pull
./docker/desplegar.sh pruebas
./docker/desplegar.sh produccion
```

Las migraciones se aplican solas al arrancar `app`.

## 5. Comprobar

```bash
docker compose -p nga-produccion ps
docker compose -p nga-produccion exec app php artisan nga:comprobar-salud --sin-avisos
```

En el backend: **Herramientas → Estado del sistema**.

## Datos y copias

- Volúmenes por entorno: `storage` (archivos y copias), `mariadb` y `redis`.
- Copias: cada noche en el volumen `storage` y, cuando se active, en OVH Object Storage. Restaurar: `docs/RESTAURACION.md`.
- Logs: `storage/logs` (14 días) y `docker compose logs` (limitados a 30 MB por contenedor).
