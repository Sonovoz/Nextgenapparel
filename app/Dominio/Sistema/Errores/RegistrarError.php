<?php

namespace App\Dominio\Sistema\Errores;

use App\Models\RegistroError;
use App\Notifications\AlertaErrorCritico;
use Error;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Throwable;

/**
 * Guarda un error en el log de errores agrupándolo por huella y, si es
 * crítico, avisa por correo sin repetir el aviso cada minuto.
 *
 * Nunca lanza excepciones: si no puede guardar el error (por ejemplo, con
 * la base de datos caída), lo deja en el log de PHP y sigue. Laravel
 * escribe además todos los errores en storage/logs.
 */
class RegistrarError
{
    private const MAX_MENSAJE = 2000;

    private const MAX_TRAZA = 15;

    private static bool $registrando = false;

    public function __invoke(Throwable $error): void
    {
        if (self::$registrando) {
            return;
        }

        self::$registrando = true;

        try {
            $this->registrar($error);
        } catch (Throwable $fallo) {
            error_log('NextGen Apparel: no se pudo registrar el error '.get_class($error).': '.$fallo->getMessage());
            $this->alertarSinBaseDeDatos($error);
        } finally {
            self::$registrando = false;
        }
    }

    public static function huella(Throwable $error): string
    {
        // El mensaje no entra en la huella: suele cambiar (ids, valores) y
        // separaría el mismo fallo en muchas filas.
        return hash('sha256', get_class($error).'|'.$error->getFile().'|'.$error->getLine());
    }

    public static function nivel(Throwable $error): NivelError
    {
        if ($error instanceof Error) {
            return NivelError::Critico;
        }

        foreach (config('errores.criticos', []) as $clase) {
            if ($error instanceof $clase) {
                return NivelError::Critico;
            }
        }

        return NivelError::Error;
    }

    private function registrar(Throwable $error): void
    {
        $ahora = now();
        $datos = [
            'mensaje' => mb_substr($error->getMessage() !== '' ? $error->getMessage() : '(sin mensaje)', 0, self::MAX_MENSAJE),
            'ultima_vez' => $ahora,
            'url' => $this->url(),
            'metodo' => $this->esConsola() ? null : request()->method(),
            'usuario_id' => auth()->id(),
        ];

        $registro = RegistroError::where('huella', self::huella($error))->first();
        $esNuevo = $registro === null;
        $reabierto = false;

        if ($esNuevo) {
            try {
                $registro = RegistroError::create($datos + [
                    'huella' => self::huella($error),
                    'nivel' => self::nivel($error),
                    'estado' => EstadoError::Pendiente,
                    'clase' => get_class($error),
                    'archivo' => $this->rutaRelativa($error->getFile()),
                    'linea' => $error->getLine(),
                    'ocurrencias' => 1,
                    'primera_vez' => $ahora,
                    'traza' => $this->traza($error),
                ]);
            } catch (UniqueConstraintViolationException) {
                // Otra petición lo ha creado a la vez: se suma a ese.
                $registro = RegistroError::where('huella', self::huella($error))->firstOrFail();
                $esNuevo = false;
            }
        }

        if (! $esNuevo) {
            $reabierto = $registro->estado === EstadoError::Resuelto;

            $registro->fill($datos + [
                'estado' => EstadoError::Pendiente,
                'resuelto_en' => $reabierto ? null : $registro->resuelto_en,
                'resuelto_por' => $reabierto ? null : $registro->resuelto_por,
            ]);
            $registro->ocurrencias = $registro->ocurrencias + 1;
            $registro->save();
        }

        $this->alertarSiProcede($registro, $esNuevo || $reabierto);
    }

    private function alertarSiProcede(RegistroError $registro, bool $esNuevoOReabierto): void
    {
        $correo = config('errores.correo_alertas');

        if ($registro->nivel !== NivelError::Critico || blank($correo)) {
            return;
        }

        $reciente = $registro->alertado_en !== null
            && $registro->alertado_en->gt(now()->subMinutes(config('errores.alerta_cada_minutos')));

        if ($reciente && ! $esNuevoOReabierto) {
            return;
        }

        // Se envía en el momento, sin cola: si el fallo es de la cola o de
        // la base de datos, el aviso tiene que salir igualmente.
        Notification::route('mail', $correo)->notifyNow(new AlertaErrorCritico($registro));

        $registro->forceFill(['alertado_en' => now()])->save();
    }

    /**
     * Si no se puede guardar el error (normalmente porque la base de datos
     * no responde), el aviso de un error crítico se envía igualmente. Para
     * no repetirlo se apunta en un archivo, no en la base de datos.
     */
    private function alertarSinBaseDeDatos(Throwable $error): void
    {
        try {
            $correo = config('errores.correo_alertas');

            if (self::nivel($error) !== NivelError::Critico || blank($correo)) {
                return;
            }

            $archivo = storage_path('framework/alertas-sin-base-de-datos.json');
            $huella = self::huella($error);
            $manejador = fopen($archivo, 'c+');

            if ($manejador === false || ! flock($manejador, LOCK_EX)) {
                return;
            }

            try {
                $enviadas = json_decode((string) stream_get_contents($manejador), true) ?: [];
                $limite = now()->subMinutes(config('errores.alerta_cada_minutos'))->getTimestamp();

                if (($enviadas[$huella] ?? 0) > $limite) {
                    return;
                }

                // forceFill: sin consultar la estructura de la tabla (la base de datos no responde).
                $registro = (new RegistroError)->forceFill([
                    'nivel' => NivelError::Critico,
                    'clase' => get_class($error),
                    'mensaje' => mb_substr($error->getMessage(), 0, self::MAX_MENSAJE),
                    'archivo' => $this->rutaRelativa($error->getFile()),
                    'linea' => $error->getLine(),
                    'ocurrencias' => 1,
                    'primera_vez' => now(),
                    'url' => $this->url(),
                ]);

                Notification::route('mail', $correo)->notifyNow(new AlertaErrorCritico($registro));

                $enviadas[$huella] = now()->getTimestamp();
                ftruncate($manejador, 0);
                rewind($manejador);
                fwrite($manejador, (string) json_encode($enviadas));
            } finally {
                flock($manejador, LOCK_UN);
                fclose($manejador);
            }
        } catch (Throwable $fallo) {
            error_log('NextGen Apparel: tampoco se pudo enviar la alerta: '.$fallo->getMessage());
        }
    }

    private function url(): ?string
    {
        if ($this->esConsola()) {
            return 'consola: '.implode(' ', array_slice($_SERVER['argv'] ?? [], 0, 3));
        }

        $url = request()->url();

        // En las acciones del backend (Livewire) la petición va a una ruta
        // interna: se guarda la página en la que estaba el usuario.
        if (Livewire::isLivewireRequest() && filled($origen = request()->headers->get('referer'))) {
            $url = strtok($origen, '?') ?: $url;
        }

        // Sin la parte de la consulta (?…): puede llevar datos personales o tokens.
        return mb_substr($url, 0, 2000);
    }

    /** Comando de consola (no una petición web). En los tests se trata como web. */
    private function esConsola(): bool
    {
        return app()->runningInConsole() && ! app()->runningUnitTests();
    }

    /** @return list<string> */
    private function traza(Throwable $error): array
    {
        $lineas = [];

        foreach (array_slice($error->getTrace(), 0, self::MAX_TRAZA) as $paso) {
            $donde = isset($paso['file']) ? $this->rutaRelativa($paso['file']).':'.($paso['line'] ?? '?') : '[interno]';
            $funcion = ($paso['class'] ?? '').($paso['type'] ?? '').($paso['function'] ?? '');
            $lineas[] = $donde.'  '.$funcion.'()';
        }

        return $lineas;
    }

    private function rutaRelativa(string $ruta): string
    {
        return ltrim(str_replace(base_path(), '', $ruta), '/\\');
    }
}
