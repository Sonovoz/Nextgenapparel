# Auditoría final · fase 9 · 9 de noviembre de 2026

Un auditor independiente revisó el proyecto con la lista de `docs/GAUNTLET.md`, solo en lo que impediría salir a producción. Las fases 1 a 8 entraron antes en `main`. Veredicto inicial: **no listo** (C1). Con las correcciones de esta entrega (D185): **listo con limitaciones**.

| Defecto | Gravedad | Corrección |
| --- | --- | --- |
| C1 · El aviso de Redsys respondía 419: la exclusión de CSRF no valía en Laravel 13, y los tests no lo veían porque ese middleware no actúa en ellos | Crítica | `preventRequestForgery(except: ['pagos/aviso'])`, con un test que prueba el middleware en modo producción |
| A1 · Producción podía cobrar con el TPV de pruebas | Alta | En producción solo se acepta Redsys con `REDSYS_ENVIRONMENT=live`; bloque Redsys en las plantillas `.env` y en `DESPLIEGUE_OVH.md` |
| M1 · Si Redsys no contestaba a una devolución, un reintento podía devolver dos veces | Media | La devolución queda «dudosa»: cuenta como devuelta, no se repite y hay que mirarla en el TPV |
| M2 · La devolución de una incidencia ocurría dentro de la transacción de la factura | Media | Primero la devolución, fuera de la transacción y con clave única; luego la rectificativa |
| M3 · Inyección de fórmulas en el CSV de facturas | Media | Las celdas de texto que empiezan por `=`, `+`, `-` o `@` llevan `'` delante |
| M4 · Faltaba HSTS | Media | Cabecera `Strict-Transport-Security` en Caddy para producción |
| Pago simulado con un formulario GET que perdía la firma | Pruebas | Redirección por enlace |

## Lo que falta para salir (cliente)

1. **Datos del TPV real** en `produccion.env`, y en el portal de Redsys la URL de notificación con «parámetros en las URLs».
2. **Prueba en el Redsys de pruebas**: pagar con tarjeta y con Bizum, cerrar la página del banco sin volver y hacer una devolución.
3. **Datos fiscales del emisor** y **revisión de los textos legales** por la asesoría, incluidos los plazos de conservación de las fotos y de la IP.
4. **Fase 10**:
   - 2FA del equipo, portado de Clave de Alma;
   - copias fuera del servidor (OVH Object Storage);
   - SMTP real y `ALERTAS_EMAIL`;
   - decidir Veri*Factu (`VERIFACTU_ACTIVO` y `VERIFACTU_PRODUCCION`).
