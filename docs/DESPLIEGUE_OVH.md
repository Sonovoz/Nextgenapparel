# Despliegue en el servidor dedicado de OVH

## Servidor compartido de OVH (D207, el que se usa)

El servidor de OVH ya tiene sus contenedores, compartidos por varios proyectos. El portal **no lleva contenedores propios**: corre dentro de `php-web` y usa la base de datos y el Redis del servidor.

```
Internet ──► npm (Nginx Proxy Manager: dominio y SSL) ──► php-web (PHP 8.3) ──► mariadb 10.11 · redis
```

| Contenedor del servidor | Para el portal |
|---|---|
| `php-web` | PHP 8.3: sirve el portal (carpeta raíz: `public/`) |
| `mariadb` | MariaDB 10.11: una base y un usuario propios para cada entorno |
| `redis` | Caché, sesiones y cola; cada entorno con su prefijo y su base (`REDIS_PREFIX`, `REDIS_DB`) |
| `npm` | Nginx Proxy Manager: el dominio, el certificado SSL y el paso a `php-web` |
| `phpmyadmin` | Para crear la base y el usuario |
| `db-backup` | Las copias de la base del servidor (además de las del portal, *Herramientas → Copias*) |
| `filebrowser` | Para subir o revisar archivos |

**Instalación (una vez por entorno: pruebas y producción):**

1. **Base de datos**: en phpMyAdmin, crea la base (`utf8mb4`) y un usuario con permisos solo sobre ella.
2. **Código**: deja el proyecto en la carpeta que sirve `php-web` (con `git clone` o con filebrowser) y apunta la raíz del sitio a su carpeta `public/`.
3. **`.env`**: copia `docker/env/produccion.env.example` (o `pruebas.env.example`) como `.env` y rellena `APP_KEY`, `APP_URL`, la base de datos (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) y el resto de claves. **Las contraseñas solo aquí**, nunca en el código ni en el chat.
4. **Dentro de `php-web`** (`docker exec -it php-web bash`, en la carpeta del proyecto):

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

5. **Tareas y colas**: no hay contenedores de cola ni de tareas. En el servidor (fuera de los contenedores), una línea de `crontab -e` por entorno; las tareas programadas procesan también la cola cada minuto:

```
* * * * * docker exec php-web php /ruta/del/proyecto/artisan schedule:run >> /dev/null 2>&1
```

6. **Dominio**: en Nginx Proxy Manager, un *Proxy Host* para el dominio hacia `php-web`, con SSL de Let's Encrypt y «Force SSL». El portal ya se fía de la cabecera del proxy (`trustProxies`) para saber que va por HTTPS.
7. **Comprobar**: *Herramientas → Servidor* (PHP 8.3, extensiones, base de datos y Redis) y *Herramientas → Estado del sistema*, todo en verde. Si falta la extensión `redis` en `php-web`, pon `REDIS_CLIENT=predis`; si falta `imagick`, pídela al administrador del servidor: sin ella no se pueden subir diseños.

## Instalación con contenedores propios (alternativa)

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
- `APP_URL`: el dominio exacto de cada entorno **con `https://`** (por ejemplo `https://nextgenapparel.es`). La aplicación rechaza las peticiones de cualquier otro dominio (D116), así que si no coincide con el de Caddy, la web responde con error 400.
- `DB_PASSWORD` (una distinta por entorno), `ALERTAS_EMAIL`.
- Producción: los datos SMTP del proveedor de correo.
- `BACKUP_ARCHIVE_PASSWORD`: **guárdala también fuera del servidor**. Sin ella no se pueden abrir las copias.

En `docker/caddy/.env`: los dos dominios y el correo para Let's Encrypt.

Estos archivos no se suben nunca al repositorio.

### Pagos con Redsys

- En `produccion.env`: `PAGOS_PASARELA=redsys`, `REDSYS_MERCHANT_CODE`, `REDSYS_TERMINAL`, `REDSYS_SECRET_KEY` y `REDSYS_ENVIRONMENT=live` (con otro valor, producción no arranca los pagos). Bizum con `REDSYS_BIZUM=true` si el banco lo activa.
- En el portal de Redsys: la URL de notificación que muestra *Ajustes → Pagos* (`https://<dominio>/pagos/aviso`) y «parámetros en las URLs».
- Antes de abrir: en pruebas (`REDSYS_ENVIRONMENT=test`), pagar con tarjeta y con Bizum, cerrar la página del banco sin volver (el pedido tiene que quedar pagado por el aviso) y hacer una devolución.

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


## Lista de puesta en marcha (fase 10)

Antes de abrir la tienda, en este orden. *Herramientas → Puesta en marcha* muestra esta lista comprobada con los datos reales y enlaza a cada ajuste (D196).

1. **Correo**: SMTP real en *Ajustes → Correos* (o `MAIL_*` en `produccion.env`), con «Enviar correo de prueba». También `ALERTAS_EMAIL`, para recibir los errores y los avisos de salud.
2. **Equipo** (desactivado por ahora, D197; se activa con `DOS_PASOS_OBLIGATORIOS=true`): cada persona entra al panel y configura la **verificación en dos pasos** con una app autenticadora. Guarda sus códigos de recuperación. Si alguien pierde el móvil: `php artisan usuarios:quitar-dos-pasos <correo>`. Si alguien olvida la contraseña: `php artisan usuarios:cambiar-contrasena <correo>`.
3. **Datos fiscales** en *Ajustes → Facturación*, y **textos legales** revisados por la asesoría en *Ajustes → Textos legales*.
4. **Redsys**: el TPV real y su prueba (ver «Pagos con Redsys»).
5. **Copias fuera del servidor**: contrata OVH Object Storage y rellena `COPIAS_EXTERNAS_*` con `COPIAS_EXTERNAS_ACTIVAS=true`. Comprueba en *Herramientas → Copias de seguridad* que se suben, y haz una prueba de restauración.
6. **Veri*Factu**: decide con la asesoría cuándo activarlo (`VERIFACTU_ACTIVO=true` y `VERIFACTU_PRODUCCION=true`). Mientras tanto, las facturas guardan su huella encadenada, pero no se envían a la AEAT.
7. **Comprobación final**: *Herramientas → Estado del sistema* en verde, un pedido real de 1 € pagado y devuelto, y el correo de cada estado recibido.
