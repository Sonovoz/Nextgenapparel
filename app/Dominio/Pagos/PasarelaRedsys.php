<?php

namespace App\Dominio\Pagos;

use App\Dominio\Pagos\Redsys\FirmaRedsys;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Redsys, el TPV virtual del banco (D181), portado de Clave de Alma (ADR 0026): tarjeta y Bizum en
 * la página segura de Redsys («Redirección»). El cobro vale con el aviso firmado o con el resultado
 * firmado que trae el cliente al volver; las devoluciones van por su API REST.
 */
class PasarelaRedsys implements Pasarela
{
    private const URLS = [
        'test' => ['pago' => 'https://sis-t.redsys.es:25443/sis/realizarPago', 'rest' => 'https://sis-t.redsys.es:25443/sis/rest/trataPeticionREST'],
        'live' => ['pago' => 'https://sis.redsys.es/sis/realizarPago', 'rest' => 'https://sis.redsys.es/sis/rest/trataPeticionREST'],
    ];

    /** Códigos que el cliente entiende (claves de lang/{es,en}/pagos.php); el resto, «el banco lo ha rechazado». */
    private const MENSAJES = ['0101' => 'caducada', '0116' => 'sin_saldo', '0129' => 'cvv', '0180' => 'no_admite', '0184' => 'titular', '0190' => 'rechazado', '9915' => 'cancelado'];

    public function __construct(
        private readonly string $comercio,
        private readonly string $terminal,
        private readonly string $clave,
        private readonly string $entorno = 'test',
        private readonly bool $tarjeta = true,
        private readonly bool $bizum = false,
        private readonly string $nombreComercio = 'Nextgenapparel',
    ) {}

    public function nombre(): string
    {
        return 'redsys';
    }

    public function metodos(): array
    {
        $metodos = ($this->tarjeta ? ['tarjeta' => 'Tarjeta'] : []) + ($this->bizum ? ['bizum' => 'Bizum'] : []);

        return $metodos === [] ? ['tarjeta' => 'Tarjeta'] : $metodos;
    }

    public function pruebas(): bool
    {
        return $this->entorno !== 'live';
    }

    public function iniciar(PeticionPago $peticion): PagoIniciado
    {
        $this->comprobarDatos();
        // Redsys no deja repetir un número de pedido: uno nuevo por visita (12 cifras, las 4 primeras del pedido).
        $orden = sprintf('%04d%08d', $peticion->pedidoId % 10000, random_int(0, 99_999_999));
        $parametros = array_filter([
            'DS_MERCHANT_AMOUNT' => (string) $peticion->importe,
            'DS_MERCHANT_ORDER' => $orden,
            'DS_MERCHANT_MERCHANTCODE' => $this->comercio,
            'DS_MERCHANT_CURRENCY' => '978',
            'DS_MERCHANT_TRANSACTIONTYPE' => '0',
            'DS_MERCHANT_TERMINAL' => $this->terminal,
            'DS_MERCHANT_MERCHANTNAME' => mb_substr($this->nombreComercio, 0, 25),
            'DS_MERCHANT_PRODUCTDESCRIPTION' => mb_substr('Pedido '.$peticion->numero, 0, 125),
            'DS_MERCHANT_CONSUMERLANGUAGE' => $peticion->idioma === 'en' ? '002' : '001',
            'DS_MERCHANT_PAYMETHODS' => $peticion->metodo === 'bizum' ? 'z' : 'C',
            'DS_MERCHANT_MERCHANTURL' => $peticion->urlAviso,
            'DS_MERCHANT_URLOK' => $peticion->urlVuelta,
            'DS_MERCHANT_URLKO' => $peticion->urlVuelta,
        ], fn (?string $v): bool => $v !== null && $v !== '');

        return new PagoIniciado($orden, self::URLS[$this->env()]['pago'], $this->firma()->sign($parametros));
    }

    public function evento(array $campos): ?EventoPasarela
    {
        if (! isset($campos['Ds_MerchantParameters'])) {
            return null;
        }
        $p = $this->firma()->verify((string) ($campos['Ds_SignatureVersion'] ?? ''), (string) $campos['Ds_MerchantParameters'], (string) ($campos['Ds_Signature'] ?? ''));
        $codigo = self::codigo($p);
        $cobrado = ctype_digit($codigo) && (int) $codigo <= 99; // 0000–0099: autorizado

        return new EventoPasarela('redsys:'.$p['Ds_Order'].':'.$codigo, $cobrado, $p['Ds_Order'],
            isset($p['Ds_Amount']) ? (int) $p['Ds_Amount'] : null, $cobrado ? null : (self::MENSAJES[$codigo] ?? 'rechazado'));
    }

    /** Devolución (operación 3) por la API REST, sobre el mismo número de pedido. Solo vale con 0900 firmado. */
    public function devolver(string $referencia, int $importe, string $clave): ResultadoDevolucion
    {
        try {
            $this->comprobarDatos();
            $respuesta = Http::asJson()->acceptJson()->timeout(30)->post(self::URLS[$this->env()]['rest'], $this->firma()->sign([
                'DS_MERCHANT_AMOUNT' => (string) $importe,
                'DS_MERCHANT_ORDER' => $referencia,
                'DS_MERCHANT_MERCHANTCODE' => $this->comercio,
                'DS_MERCHANT_CURRENCY' => '978',
                'DS_MERCHANT_TRANSACTIONTYPE' => '3',
                'DS_MERCHANT_TERMINAL' => $this->terminal,
            ]));
        } catch (PagoNoDisponible $e) {
            return new ResultadoDevolucion(false, null, $e->getMessage());
        } catch (ConnectionException $e) {
            // Pudo hacerla sin que llegara la respuesta: repetirla podría devolver dos veces (auditoría final, M1).
            return new ResultadoDevolucion(false, null, 'Redsys no ha respondido: '.$e->getMessage(), dudosa: true);
        }
        $cuerpo = (array) $respuesta->json();
        if (isset($cuerpo['errorCode'])) {
            return new ResultadoDevolucion(false, null, 'Redsys ha rechazado la devolución ('.$cuerpo['errorCode'].').');
        }
        try {
            $p = $this->firma()->verify((string) ($cuerpo['Ds_SignatureVersion'] ?? ''), (string) ($cuerpo['Ds_MerchantParameters'] ?? ''), (string) ($cuerpo['Ds_Signature'] ?? ''));
        } catch (AvisoNoValido $e) {
            return new ResultadoDevolucion(false, null, 'Respuesta de Redsys no válida: '.$e->getMessage());
        }
        $codigo = self::codigo($p);

        return $codigo === '0900'
            ? new ResultadoDevolucion(true, $referencia.':'.($p['Ds_AuthorisationCode'] ?? substr(hash('sha256', $clave), 0, 8)))
            : new ResultadoDevolucion(false, null, 'Redsys no ha hecho la devolución (código '.$codigo.').');
    }

    /** @param  array<string, string>  $p */
    private static function codigo(array $p): string
    {
        return str_pad((string) ($p['Ds_Response'] ?? '9999'), 4, '0', STR_PAD_LEFT);
    }

    private function firma(): FirmaRedsys
    {
        return new FirmaRedsys($this->clave);
    }

    private function env(): string
    {
        return $this->entorno === 'live' ? 'live' : 'test';
    }

    private function comprobarDatos(): void
    {
        if ($this->comercio === '' || $this->clave === '') {
            throw new PagoNoDisponible('Faltan los datos del TPV de Redsys (REDSYS_MERCHANT_CODE y REDSYS_SECRET_KEY en el .env).');
        }
    }
}
