# NextGen Apparel — instrucciones del proyecto

Portal de pedidos de prendas personalizadas con impresión DTI (camisetas, polos y sudaderas) para pequeños diseñadores. Laravel + Filament, en Docker sobre un servidor dedicado de OVH.

**Lee siempre antes de trabajar:**
- `docs/FICHA.md`: alcance, modelo de datos, reglas de negocio y fases. Es la fuente de verdad.
- `docs/DECISIONES.md`: decisiones ya tomadas y su motivo. No las reabras sin que lo pida el usuario.
- `docs/GAUNTLET.md`: protocolo de calidad obligatorio para todo el desarrollo.

## Reglas permanentes

1. **Idioma:** responde y documenta en castellano de España. La interfaz del portal es bilingüe (es/en).
2. **Lógica antes que interfaz.** Ninguna pantalla se construye sobre lógica sin tests. El modelo de datos y el motor de precios se validan primero.
3. **La IA nunca calcula precios, mínimos ni impuestos.** Los calcula el motor de precios en PHP, de forma determinista y con tests. La IA solo consulta y explica sus resultados.
4. **Todo lo configurable se configura en el backend:** reglas de mínimos, precios, ofertas, envíos, plazos, estados visibles para el cliente, plantillas de correo, SMTP y proveedor de IA. No se fijan valores de negocio en el código.
5. **Backend con las funciones y la organización de WooCommerce** (Pedidos, Productos variables, Clientes, Ajustes → Impuestos/Envío/Pagos/Correos, Analíticas, roles). El equipo lleva años con WooCommerce y debe reconocer las funciones. Se parece en funciones, no es una copia visual.
6. **Autosuficiencia:** colas con reintentos, tareas programadas, comprobación de salud, alertas por correo, copias de seguridad automáticas y funcionamiento sin dependencias críticas (si la IA cae, el portal sigue vendiendo).
7. **Dinero sin errores de redondeo:** importes en céntimos (enteros); precios guardados sin IVA más su tipo de impuesto, mostrados con IVA.
8. **Alcance por versiones:** construir la versión 1 completa antes de cualquier función de la versión 2. La base de datos se diseña pensando en ambas.
9. **No inventes resultados:** pruebas, métricas o auditorías no ejecutadas se declaran como no ejecutadas.
10. **Secretos fuera del código:** claves de Redsys, SMTP e IA solo en variables de entorno.
11. **Una sesión de trabajo por fase.** Al cerrar una fase, actualiza el estado en `docs/FICHA.md` (sección «Estado») y registra en `docs/DECISIONES.md` cualquier decisión nueva.

## Protocolo del instructor (cómo se verifica, D74)

El **instructor** (`.claude/agents/instructor.md`) planifica cada fase, asigna a cada paso un nivel de verificación y hace la revisión adversarial al cerrarla. La verificación es **proporcional al riesgo**:

| Nivel | Cuándo | Qué se hace al terminar el paso |
|---|---|---|
| **N1 Ligero** | Textos, estilos, documentación, ajustes sin lógica | Tests afectados + Pint. Sin navegador |
| **N2 Normal** | Pantallas y funciones nuevas sin dinero ni permisos | Suite completa + **una** pasada de navegador del flujo principal (dominio `.test`) |
| **N3 Riguroso** | Dinero, impuestos, facturas, permisos, datos personales, migraciones, copias, despliegue | N2 + migraciones en MariaDB estricta (configuración de XAMPP) + revisión del instructor |

Reglas:
1. **Una sola verificación, al final del paso.** Nada de capturas ni comprobaciones intermedias. Si algo falla, se corrige y se repite solo lo afectado.
2. **Tests útiles:** cada regla de negocio y cada fallo real encontrado tiene su test. No se prueba lo que ya garantiza el framework; los casos parecidos van juntos (proveedores de datos).
3. **Revisión del cliente en Herd** solo cuando hay algo visible, agrupando 2 o 3 pasos, con un máximo de 3 puntos que comprobar.
4. **Gauntlet completo** (dos rondas adversariales e informe de la sección 14) solo al cerrar la fase. Por paso basta el informe breve.
5. **Informe breve por paso** (unas 10 líneas): qué incluye, nivel aplicado, qué no se ha podido probar y los comandos para verlo.

## Pila técnica

- PHP 8.x, Laravel (última versión estable), Filament (backend), Livewire (frontend).
- MariaDB/MySQL, Redis (colas, caché, sesiones), contenedores de cola (`queue:work`) y de tareas programadas (`schedule:work`).
- Imagick (análisis de PNG y maquetas), generación de PDF, ZIP nativo.
- Local: Herd + SQLite. Servidor: Docker Compose con entornos de **pruebas** y **producción** en el mismo dedicado, detrás de Caddy (`docs/DESPLIEGUE_OVH.md`).
