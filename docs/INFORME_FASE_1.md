# Informe de calidad — Fase 1 (Base)

Según la sección 14 de `docs/GAUNTLET.md`. Rama `fase-1-base`, 6 de noviembre de 2026.

## Estado del proyecto

**Terminado con limitaciones.** Los tres criterios de aceptación de la fase se cumplen en un entorno de pruebas Docker montado desde cero; falta desplegarlo en el servidor real de OVH, que todavía no está preparado (dominios, SMTP y Object Storage pendientes).

| Criterio de aceptación (ficha, sección 15) | Resultado | Cómo se comprobó |
|---|---|---|
| Los contenedores arrancan desde cero con un comando | **Cumplido** | `docker compose up --wait` con volúmenes vacíos: los 6 contenedores sanos en 33 s, migraciones aplicadas solas |
| Se hace y se restaura una copia de seguridad | **Cumplido** | Dentro de Docker: copia con MariaDB y prueba de restauración (16 tablas leídas). También con SQLite y con cifrado AES-256 |
| Un error provocado aparece en el log y genera una alerta | **Cumplido** | `nga:error-prueba` dentro de Docker: registrado como crítico y alerta enviada (`alertado_en` relleno) |

## Pasos

| Paso | Contenido | Verificado por el cliente en Herd |
|---|---|---|
| 1.1 | Laravel 13, idiomas es/en, portada provisional | Sí |
| 1.2 | Backend Filament con menú tipo WooCommerce | Sí |
| 1.3 | Roles, permisos y usuarios | Sí |
| 1.4 | Log de errores, alertas y registro de actividad | Sí |
| 1.5 | Estado del sistema y tareas programadas | Sí |
| 1.6 | Copias de seguridad con prueba de restauración | Pendiente |
| 1.7 | Docker para pruebas y producción, Caddy, despliegue | No aplica (servidor) |

## Rondas de revisión

Una revisión por paso (tests, navegador con dominio `.test` y revisión del código antes de entregar), más la revisión en Herd del cliente. Total: 7 rondas internas y 6 del cliente. La revisión no la hizo un auditor independiente: la hizo el mismo constructor en una pasada separada.

## Defectos encontrados y corregidos

| # | Gravedad | Defecto | Dónde | Corrección | Cómo se comprueba |
|---|---|---|---|---|---|
| 1 | Crítica | Error 419 al entrar en el backend en Herd | Panel de Filament | Solo el middleware de idioma es persistente (D45) | Test de regresión + navegador con dominio `.test` |
| 2 | Crítica | La migración del log de errores fallaba en la MariaDB de XAMPP | Migración `registro_errores` | `DATETIME` en lugar de `TIMESTAMP` (D59) | Migraciones con la configuración de XAMPP + test de migraciones |
| 3 | Alta | El contador del menú tumbaba el backend si faltaba la tabla | `RegistroErrorResource` | Si no puede contar, no se muestra | Test |
| 4 | Alta | Sin alerta si la base de datos estaba caída | Log de errores | Aviso de respaldo con control en archivo (D54) | Test |
| 5 | Alta | El paquete de salud no avisaría con la base de datos caída | Paso 1.5 | Comprobación de salud propia (D61) | Test con base de datos caída |
| 6 | Alta | El JavaScript de Livewire daba 404 en Nginx: no se podía entrar en el backend | `docker/nginx/default.conf` | Los estáticos que no existen pasan a Laravel | Navegador contra Docker |
| 7 | Media | La lista de tareas automáticas salía vacía en la web | Estado del sistema | Carga de `routes/console.php` en la página | Test + navegador |
| 8 | Media | Avatar con servicio externo (ui-avatars.com) | Panel | Avatar generado en el servidor (D42) | Test sin recursos externos |
| 9 | Media | La página de un error guardaba la ruta interna de Livewire | Log de errores | Se guarda la página del usuario (D55) | Test |
| 10 | Media | Copias de SQLite imposibles en Windows (sin `sqlite3`) | Copias | `VACUUM INTO` (D65) | Copia y restauración con SQLite |
| 11 | Media | Horizon habría roto `composer install` en Windows | Pila | Contenedor `cola` con `queue:work` (D69) | Cola procesando en Docker |
| 12 | Baja | Aviso de PHP al formatear contadores | Log de errores | `decimalPlaces: 0` | Sin avisos en consola |

## Pruebas superadas

- **145 tests automáticos** (PHPUnit), también en GitHub Actions con PHP 8.3 y 8.5.
- Migraciones contra MariaDB con la configuración estricta de XAMPP.
- Navegador (Playwright) con dominios `.test`: portada, acceso, menú por rol, log de errores, actividad, estado del sistema.
- Docker: arranque desde cero, acceso al backend, comprobación de salud (MariaDB, Redis, tareas y cola en sus contenedores), copia y restauración, error provocado, reinicio automático de un contenedor caído, contenedores sin root.

## Pruebas no ejecutadas

| Prueba | Motivo |
|---|---|
| Despliegue en el servidor de OVH | El servidor y los dominios aún no están preparados |
| Certificados SSL con Caddy | Necesita dominios reales apuntando al servidor |
| Envío real de correos por SMTP | Falta el proveedor SMTP |
| Copia fuera del servidor (Object Storage) | No contratado (D37) |
| Mailpit en pruebas | Este entorno no pudo descargar su imagen (límite de Docker Hub) |
| Construcción de la imagen con `composer install` dentro | Este entorno bloquea las descargas desde GitHub; se construyó con las dependencias ya descargadas. En OVH funciona sin ese bloqueo |
| Paso 1.6 en Herd | Pendiente de que el cliente lo revise |
| Auditoría Lighthouse y de accesibilidad completa | Se hará en la fase 9 sobre el portal real |

## Riesgos pendientes

| Riesgo | Gravedad | Acción |
|---|---|---|
| La imagen de la aplicación ocupa 1,6 GB | Baja | Optimizarla antes de la puesta en marcha |
| Sin contraseña de copias guardada fuera del servidor no se podrán restaurar | Alta (al desplegar) | Guardarla en un gestor de contraseñas al configurar el servidor |
| El primer despliegue real puede destapar diferencias del servidor | Media | Desplegar primero el entorno de pruebas y repasar `docs/DESPLIEGUE_OVH.md` |

## Despliegue

Pasos en `docs/DESPLIEGUE_OVH.md`. En resumen: instalar Docker, clonar el repositorio, rellenar `docker/env/*.env` y `docker/caddy/.env`, arrancar Caddy y ejecutar `./docker/desplegar.sh pruebas` y `./docker/desplegar.sh produccion`. Restaurar copias: `docs/RESTAURACION.md`.
