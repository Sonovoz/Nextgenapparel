<?php

namespace App\Notifications;

use App\Filament\Resources\RegistroErrors\RegistroErrorResource;
use App\Models\RegistroError;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Correo de aviso de un error crítico.
 */
class AlertaErrorCritico extends Notification
{
    public function __construct(public RegistroError $registro) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->registro;
        $entorno = app()->environment();

        return (new MailMessage)
            ->error()
            ->subject("[{$entorno}] Error crítico en NextGen Apparel: ".Str::limit(class_basename($r->clase).': '.$r->mensaje, 80))
            ->greeting('Error crítico')
            ->line('Se ha producido un error crítico en NextGen Apparel.')
            ->line('**Error:** '.class_basename($r->clase))
            ->line('**Mensaje:** '.Str::limit($r->mensaje, 500))
            ->line('**Dónde:** '.($r->archivo ? $r->archivo.':'.$r->linea : 'desconocido'))
            ->line('**Página:** '.($r->url ?? 'desconocida'))
            ->line('**Veces:** '.$r->ocurrencias.' (primera: '.$r->primera_vez->format('d/m/Y H:i').')')
            ->line("**Entorno:** {$entorno}")
            ->when(! $r->exists, fn (MailMessage $m) => $m->line('**No se ha podido guardar en el log de errores**: probablemente la base de datos no responde. Revisa storage/logs/laravel.log en el servidor.'))
            ->action('Ver el log de errores', $r->exists
                ? RegistroErrorResource::getUrl('view', ['record' => $r], panel: 'admin')
                : RegistroErrorResource::getUrl('index', panel: 'admin'))
            ->line('No recibirás otro aviso de este mismo error durante '.config('errores.alerta_cada_minutos').' minutos, salvo que se marque como resuelto y vuelva a ocurrir.')
            ->salutation('NextGen Apparel · aviso automático');
    }
}
