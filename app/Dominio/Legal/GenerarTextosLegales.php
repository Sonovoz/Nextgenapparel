<?php

namespace App\Dominio\Legal;

use App\Dominio\Carrito\CarritoActual;
use App\Dominio\Facturacion\Emisor;
use App\Dominio\Precios\Formato;
use App\Models\Ajuste;
use App\Models\TextoLegal;
use App\Models\ZonaEnvio;
use Illuminate\Support\Facades\DB;

/**
 * Genera los textos legales con datos reales del sistema (paso 8.1, D183), portado de Clave de Alma
 * (ADR 0020) y adaptado a un solo vendedor y a prendas personalizadas (sin desistimiento, art. 103.c).
 * Lo que falta sale como «[pendiente: …]». Son una base: los revisa la asesoría.
 */
final class GenerarTextosLegales
{
    /** @return int versiones nuevas (solo donde cambia el texto) */
    public function __invoke(): int
    {
        $nuevas = 0;
        foreach (array_keys(config('idiomas.disponibles')) as $idioma) {
            foreach (PaginaLegal::cases() as $pagina) {
                [$contenido, $pendientes] = $this->generar($pagina, $idioma);
                $nuevas += DB::transaction(function () use ($pagina, $idioma, $contenido, $pendientes): int {
                    $actual = TextoLegal::where('pagina', $pagina->value)->where('idioma', $idioma)->lockForUpdate()->latest('version')->first();
                    if ($actual !== null && $actual->contenido === $contenido) {
                        return 0;
                    }
                    TextoLegal::create(['pagina' => $pagina, 'idioma' => $idioma, 'version' => ($actual->version ?? 0) + 1, 'contenido' => $contenido,
                        'pendientes' => $pendientes === [] ? null : $pendientes, 'generado_en' => now()]);

                    return 1;
                });
            }
        }

        return $nuevas;
    }

    /** @return array{0: string, 1: list<string>} */
    public function generar(PaginaLegal $pagina, string $idioma = 'es'): array
    {
        $pendientes = [];
        // El dato o una marca visible de que falta (y se apunta en la lista).
        $f = function (?string $valor, string $etiqueta) use (&$pendientes): string {
            $valor = trim((string) $valor);
            if ($valor === '') {
                $pendientes[] = $etiqueta;

                return '**[pendiente: '.$etiqueta.']**';
            }

            return e($valor);
        };
        $emisor = Emisor::datos();
        $emisor['correo'] = $emisor['correo'] ?: Ajuste::obtener('tienda.correo_contacto');

        $contenido = view('legal.'.$idioma.'.'.$pagina->value, [
            'f' => $f,
            'e' => $emisor,
            'tienda' => (string) Ajuste::obtener('tienda.nombre'),
            'web' => rtrim((string) config('app.url'), '/'),
            'envios' => ZonaEnvio::where('activa', true)->orderBy('prioridad')->get()->map(fn (ZonaEnvio $z) => [
                'zona' => $z->getTranslation('nombre', $idioma), 'dias' => $z->plazo_dias, 'gratis' => $z->gratis_desde === null ? null : Formato::euros($z->gratis_desde, $idioma),
            ])->all(),
            'diasIncidencia' => (int) Ajuste::obtener('incidencias.dias'),
            'diasSinPago' => (int) Ajuste::obtener('pedidos.dias_sin_pago'),
            'cookieSesion' => (string) config('session.cookie'),
            'cookieCarrito' => CarritoActual::COOKIE,
        ])->render();

        return [trim($contenido)."\n", array_values(array_unique($pendientes))];
    }
}
