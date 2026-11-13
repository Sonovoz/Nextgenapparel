<?php

namespace App\Dominio\Sistema\Salud;

use App\Dominio\Sistema\Salud\Comprobaciones\Almacenamiento;
use App\Dominio\Sistema\Salud\Comprobaciones\BaseDeDatos;
use App\Dominio\Sistema\Salud\Comprobaciones\Cache;
use App\Dominio\Sistema\Salud\Comprobaciones\Colas;
use App\Dominio\Sistema\Salud\Comprobaciones\Comprobacion;
use App\Dominio\Sistema\Salud\Comprobaciones\Configuracion;
use App\Dominio\Sistema\Salud\Comprobaciones\CopiasSeguridad;
use App\Dominio\Sistema\Salud\Comprobaciones\Correo;
use App\Dominio\Sistema\Salud\Comprobaciones\ErroresCriticos;
use App\Dominio\Sistema\Salud\Comprobaciones\EspacioDisco;
use App\Dominio\Sistema\Salud\Comprobaciones\Imagenes;
use App\Dominio\Sistema\Salud\Comprobaciones\Limpieza;
use App\Dominio\Sistema\Salud\Comprobaciones\TareasProgramadas;
use App\Notifications\AlertaSalud;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Ejecuta las comprobaciones de salud, guarda el resultado y avisa por
 * correo cuando algo falla (y cuando vuelve a funcionar).
 *
 * El estado se guarda en un archivo (storage/framework/salud.json), no en
 * la base de datos, para que todo funcione aunque la base de datos caiga.
 */
class ComprobarSalud
{
    /** @return list<class-string<Comprobacion>> */
    public static function comprobaciones(): array
    {
        return [
            BaseDeDatos::class,
            Cache::class,
            TareasProgramadas::class,
            Colas::class,
            Correo::class,
            EspacioDisco::class,
            Almacenamiento::class, // D231
            Limpieza::class,       // D231
            Imagenes::class,
            CopiasSeguridad::class,
            ErroresCriticos::class,
            Configuracion::class,
        ];
    }

    /** @return list<Resultado> */
    public function __invoke(bool $avisar = true): array
    {
        $resultados = array_map(fn (string $clase) => $this->ejecutar(app($clase)), self::comprobaciones());

        $estado = self::leerEstado();
        $estado['comprobado_en'] = now()->getTimestamp();
        $estado['resultados'] = array_map(fn (Resultado $r) => $r->aArray(), $resultados);

        if ($avisar) {
            $estado['alerta'] = $this->avisarSiProcede($resultados, $estado['alerta'] ?? null);
        }

        self::guardarEstado($estado);

        return $resultados;
    }

    private function ejecutar(Comprobacion $comprobacion): Resultado
    {
        try {
            [$estado, $mensaje] = $comprobacion->comprobar();
        } catch (Throwable $error) {
            [$estado, $mensaje] = [EstadoSalud::Fallo, 'Error al comprobar: '.mb_substr($error->getMessage(), 0, 300)];
        }

        return new Resultado($comprobacion->clave(), $comprobacion->nombre(), $estado, $mensaje);
    }

    /**
     * @param  list<Resultado>  $resultados
     * @param  array{enviada_en: int, claves: list<string>}|null  $alerta
     * @return array{enviada_en: int, claves: list<string>}|null
     */
    private function avisarSiProcede(array $resultados, ?array $alerta): ?array
    {
        $fallos = array_values(array_map(
            fn (Resultado $r) => $r->clave,
            array_filter($resultados, fn (Resultado $r) => $r->estado === EstadoSalud::Fallo),
        ));

        if ($fallos === []) {
            if ($alerta !== null) {
                $this->enviar(new AlertaSalud($resultados, recuperado: true));
            }

            return null;
        }

        $nuevos = array_diff($fallos, $alerta['claves'] ?? []);
        $caducada = $alerta === null
            || CarbonImmutable::createFromTimestamp($alerta['enviada_en'])
                ->addMinutes(config('errores.alerta_cada_minutos'))
                ->isPast();

        if ($nuevos === [] && ! $caducada) {
            return ['enviada_en' => $alerta['enviada_en'], 'claves' => $fallos];
        }

        $this->enviar(new AlertaSalud($resultados, recuperado: false));

        return ['enviada_en' => now()->getTimestamp(), 'claves' => $fallos];
    }

    private function enviar(AlertaSalud $aviso): void
    {
        $correo = config('errores.correo_alertas');

        if (blank($correo)) {
            return;
        }

        try {
            Notification::route('mail', $correo)->notifyNow($aviso);
        } catch (Throwable $error) {
            error_log('NextGen Apparel: no se pudo enviar el aviso de salud: '.$error->getMessage());
        }
    }

    /** @return array{comprobado_en?: int, resultados?: list<array>, alerta?: array|null} */
    public static function leerEstado(): array
    {
        $archivo = self::archivo();

        if (! is_file($archivo)) {
            return [];
        }

        return json_decode((string) @file_get_contents($archivo), true) ?: [];
    }

    /** Resultados de la última comprobación guardada. @return list<Resultado> */
    public static function ultimosResultados(): array
    {
        return array_map(
            fn (array $datos) => Resultado::desdeArray($datos),
            self::leerEstado()['resultados'] ?? [],
        );
    }

    public static function ultimaComprobacion(): ?CarbonImmutable
    {
        $marca = self::leerEstado()['comprobado_en'] ?? null;

        return $marca ? CarbonImmutable::createFromTimestamp($marca, config('app.timezone')) : null;
    }

    private static function guardarEstado(array $estado): void
    {
        file_put_contents(self::archivo(), (string) json_encode($estado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }

    public static function archivo(): string
    {
        return storage_path('framework/salud.json');
    }
}
