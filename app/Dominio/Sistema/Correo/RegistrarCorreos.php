<?php

namespace App\Dominio\Sistema\Correo;

use App\Models\Pedido;
use App\Models\RegistroCorreo;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Throwable;

/**
 * D235: apunta en registro_correos cada correo de la tienda: al entrar en la cola, al enviarse, si falla
 * (con el error del SMTP y los reintentos) y si no se manda (p. ej. correo del cliente sin confirmar).
 * Nunca rompe un envío: cualquier fallo del registro se ignora.
 */
final class RegistrarCorreos
{
    public static function escuchar(): void
    {
        Event::listen(JobQueued::class, function (JobQueued $e): void {
            if ($e->job instanceof SendQueuedNotifications && in_array('mail', (array) $e->job->channels, true)) {
                foreach ($e->job->notifiables as $destino) {
                    self::guardar($e->job->notification, $destino, ['estado' => 'en_cola']);
                }
            }
        });
        Event::listen(NotificationSending::class, function (NotificationSending $e): void {
            if ($e->channel === 'mail') {
                self::guardar($e->notification, $e->notifiable, ['estado' => 'enviando']);
            }
        });
        Event::listen(NotificationSent::class, function (NotificationSent $e): void {
            if ($e->channel === 'mail') {
                $asunto = rescue(fn () => $e->response?->getOriginalMessage()->getSubject(), null, false);
                self::guardar($e->notification, $e->notifiable, ['estado' => 'enviado', 'enviado_en' => now(), 'error' => null, ...(is_string($asunto) ? ['asunto' => Str::limit($asunto, 250)] : [])]);
            }
        });
        Event::listen(NotificationFailed::class, function (NotificationFailed $e): void {
            if ($e->channel === 'mail') {
                self::guardar($e->notification, $e->notifiable, ['estado' => 'fallido', 'error' => Str::limit(json_encode($e->data, JSON_UNESCAPED_UNICODE) ?: 'Error', 2000)]);
            }
        });
        Event::listen(JobExceptionOccurred::class, fn (JobExceptionOccurred $e) => self::fallo($e->job->payload(), $e->exception, 'reintentando'));
        Event::listen(JobFailed::class, fn (JobFailed $e) => self::fallo($e->job->payload(), $e->exception, 'fallido'));
    }

    /** Un correo que no se manda a propósito (p. ej. el cliente no ha confirmado su correo). */
    public static function noEnviado(Notification $notificacion, string $destinatario, string $motivo): void
    {
        self::guardar($notificacion, (new AnonymousNotifiable)->route('mail', $destinatario), ['estado' => 'no_enviado', 'error' => $motivo]);
    }

    /** @param  array<string, mixed>  $payload */
    private static function fallo(array $payload, Throwable $error, string $estado): void
    {
        try {
            $trabajo = unserialize($payload['data']['command'] ?? '');
            if (! $trabajo instanceof SendQueuedNotifications || ! in_array('mail', (array) $trabajo->channels, true)) {
                return;
            }
            foreach ($trabajo->notifiables as $destino) {
                $registro = self::guardar($trabajo->notification, $destino, ['estado' => $estado, 'error' => Str::limit($error->getMessage(), 2000)]);
                $registro?->increment('intentos');
            }
        } catch (Throwable) {
            // el registro nunca debe romper la cola
        }
    }

    /** @param  array<string, mixed>  $datos */
    private static function guardar(Notification $notificacion, mixed $destino, array $datos): ?RegistroCorreo
    {
        try {
            $para = $destino instanceof AnonymousNotifiable ? $destino->routeNotificationFor('mail') : (is_object($destino) && method_exists($destino, 'routeNotificationFor') ? $destino->routeNotificationFor('mail', $notificacion) : null);
            $para = is_array($para) ? (string) array_key_first($para) : (string) $para;
            if ($para === '') {
                return null;
            }
            $pedido = property_exists($notificacion, 'pedido') && $notificacion->pedido instanceof Pedido ? $notificacion->pedido->id : null;
            $clave = ['notificacion_id' => $notificacion->id ?: null, 'destinatario' => mb_strtolower($para)];
            $registro = $notificacion->id ? RegistroCorreo::firstOrNew($clave) : new RegistroCorreo($clave);
            $registro->fill(['tipo' => class_basename($notificacion), 'pedido_id' => $registro->pedido_id ?? $pedido, ...$datos])->save();

            return $registro;
        } catch (Throwable) {
            return null;
        }
    }
}
