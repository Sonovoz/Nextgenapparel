<?php

namespace App\Dominio\Sistema\PuestaEnMarcha;

use App\Dominio\Asistente\ClaveIa;
use App\Dominio\Legal\PaginaLegal;
use App\Dominio\Sistema\Acceso\Rol;
use App\Dominio\Sistema\Copias\Copias;
use App\Dominio\Sistema\Correo\Smtp;
use App\Dominio\Sistema\Salud\ComprobarSalud;
use App\Dominio\Sistema\Servidor\InformeServidor;
use App\Filament\Pages\AjustesCorreos;
use App\Filament\Pages\AjustesFacturacion;
use App\Filament\Pages\AjustesGenerales;
use App\Filament\Pages\AjustesIa;
use App\Filament\Pages\AjustesLegales;
use App\Filament\Pages\AjustesPagos;
use App\Filament\Pages\CopiasSeguridad;
use App\Filament\Pages\EstadoSistema;
use App\Filament\Pages\Servidor;
use App\Filament\Resources\Catalogo\Prendas\PrendaResource;
use App\Filament\Resources\Pedidos\PedidoResource;
use App\Filament\Resources\Precios\ZonaEnvioResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Ajuste;
use App\Models\Pago;
use App\Models\Prenda;
use App\Models\TextoLegal;
use App\Models\User;
use App\Models\ZonaEnvio;

/**
 * Herramientas → Puesta en marcha (D196): los pasos para dejar la tienda lista, en orden, y si cada uno está
 * hecho, comprobado con los datos reales (nunca marcado a mano). Lo del .env solo se comprueba si está, sin
 * mostrar valores. Solo lectura.
 */
final class PuestaEnMarcha
{
    /** @return list<Paso> */
    public static function pasos(): array
    {
        $emisor = (array) Ajuste::obtener('facturacion.emisor');
        $smtp = Smtp::guardado();
        $redsys = (array) config('pagos.redsys');
        $equipo = User::role(Rol::valores())->where('activo', true)->get();
        $sinDosPasos = $equipo->filter(fn (User $u) => blank($u->app_authentication_secret))->count();
        $sinRevisar = 0;
        foreach (PaginaLegal::cases() as $pagina) {
            foreach (array_keys(config('idiomas.disponibles')) as $idioma) {
                $sinRevisar += TextoLegal::actual($pagina, $idioma)?->revisado_en === null ? 1 : 0;
            }
        }
        $salud = ComprobarSalud::leerEstado()['resultados'] ?? [];
        $prueba = Copias::ultimaPrueba();

        return [
            new Paso('Servidor preparado', 'PHP 8.3 con sus extensiones, la base de datos y Redis respondiendo (Herramientas → Servidor).', Servidor::class,
                rescue(fn () => InformeServidor::listo(), false, report: false)),
            new Paso('Datos de la tienda', 'Nombre de la tienda y correo de contacto.', AjustesGenerales::class,
                filled(Ajuste::obtener('tienda.correo_contacto'))),
            new Paso('Correo de salida (SMTP)', 'Servidor, usuario y remitente en Ajustes → Correos; pulsa «Enviar correo de prueba».', AjustesCorreos::class,
                filled($smtp['servidor'] ?? null) || (config('mail.default') === 'smtp' && ! in_array(config('mail.mailers.smtp.host'), [null, '', '127.0.0.1', 'localhost', 'mailpit'], true))),
            new Paso('Correo de alertas', 'ALERTAS_EMAIL en el .env del servidor: ahí llegan los errores y los avisos de salud.', null,
                filled(config('errores.correo_alertas'))),
            new Paso('Verificación en dos pasos del equipo', $sinDosPasos === 0 ? 'Todo el equipo la tiene.' : "Faltan {$sinDosPasos} personas: cada una la activa en su perfil.", UserResource::class,
                $equipo->isNotEmpty() && $sinDosPasos === 0),
            new Paso('Datos fiscales', 'Razón social, NIF y dirección del emisor de las facturas.', AjustesFacturacion::class,
                filled($emisor['razon_social'] ?? null) && filled($emisor['nif'] ?? null) && filled($emisor['direccion'] ?? null)),
            new Paso('Textos legales revisados', $sinRevisar === 0 ? 'Revisados por la asesoría en todos los idiomas.' : "Quedan {$sinRevisar} textos (página e idioma) sin la revisión de la asesoría.", AjustesLegales::class,
                $sinRevisar === 0),
            new Paso('Catálogo', 'Al menos una prenda activa con variaciones a la venta y una zona de impresión.', PrendaResource::class,
                Prenda::where('activa', true)->whereHas('variantes', fn ($q) => $q->aLaVenta())->whereHas('zonas')->exists()),
            new Paso('Envíos', 'Al menos una zona de envío activa con sus tarifas.', ZonaEnvioResource::class,
                ZonaEnvio::where('activa', true)->whereHas('tarifas')->exists()),
            new Paso('Redsys real', 'El TPV del banco en el .env (REDSYS_*, con REDSYS_ENVIRONMENT=live).', AjustesPagos::class,
                config('pagos.pasarela') === 'redsys' && ($redsys['entorno'] ?? null) === 'live' && filled($redsys['comercio'] ?? null) && filled($redsys['clave'] ?? null)),
            new Paso('Copias fuera del servidor', 'COPIAS_EXTERNAS_* en el .env y una prueba de restauración correcta.', CopiasSeguridad::class,
                in_array('copias_externas', (array) config('backup.backup.destination.disks'), true) && ($prueba['correcto'] ?? false) === true),
            new Paso('Veri*Factu', 'Se activa cuando lo decida la asesoría (VERIFACTU_ACTIVO y VERIFACTU_PRODUCCION).', AjustesFacturacion::class,
                (bool) config('facturacion.verifactu.activo') && (bool) config('facturacion.verifactu.produccion')),
            new Paso('Estado del sistema en verde', 'Ninguna comprobación de salud en fallo.', EstadoSistema::class,
                $salud !== [] && collect($salud)->every(fn ($r) => ($r['estado'] ?? null) !== 'fallo')),
            new Paso('Pedido real de prueba', 'Un pedido real pequeño pagado con Redsys (y después devuelto).', PedidoResource::class,
                Pago::where('pruebas', false)->where('estado', 'cobrado')->exists()),
            new Paso('Abrir a los buscadores', 'Activa la indexación en Ajustes → General al abrir la tienda.', AjustesGenerales::class,
                (bool) Ajuste::obtener('seo.indexar')),
            new Paso('Asistente con IA', 'OPENAI_API_KEY en el .env y activarlo en Ajustes → IA.', AjustesIa::class,
                ClaveIa::configurada() && (bool) Ajuste::obtener('ia.activa'), opcional: true),
        ];
    }

    /** Pasos obligatorios que faltan (para el número del menú). */
    public static function pendientes(): int
    {
        return collect(self::pasos())->filter(fn (Paso $p) => ! $p->hecho && ! $p->opcional)->count();
    }
}
