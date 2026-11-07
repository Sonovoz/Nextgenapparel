<?php

namespace App\Notifications;

use App\Dominio\Sistema\Salud\EstadoSalud;
use App\Dominio\Sistema\Salud\Resultado;
use App\Filament\Pages\EstadoSistema;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso de que algo del sistema ha dejado de funcionar, o de que vuelve a
 * funcionar todo.
 */
class AlertaSalud extends Notification
{
    /** @param  list<Resultado>  $resultados */
    public function __construct(public array $resultados, public bool $recuperado) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $entorno = app()->environment();
        $fallos = array_filter($this->resultados, fn (Resultado $r) => $r->estado === EstadoSalud::Fallo);

        $correo = (new MailMessage)->salutation('NextGen Apparel · aviso automático');

        if ($this->recuperado) {
            return $correo
                ->success()
                ->subject("[{$entorno}] NextGen Apparel: todo vuelve a funcionar")
                ->greeting('Todo vuelve a funcionar')
                ->line('Las comprobaciones del sistema vuelven a estar correctas.')
                ->action('Ver el estado del sistema', EstadoSistema::getUrl(panel: 'admin'));
        }

        $correo->error()
            ->subject("[{$entorno}] NextGen Apparel: ".count($fallos).' '.(count($fallos) === 1 ? 'fallo' : 'fallos').' en el sistema')
            ->greeting('Algo no funciona')
            ->line('Estas comprobaciones del sistema han fallado:');

        foreach ($fallos as $fallo) {
            $correo->line("**{$fallo->nombre}:** {$fallo->mensaje}");
        }

        return $correo
            ->action('Ver el estado del sistema', EstadoSistema::getUrl(panel: 'admin'))
            ->line('Recibirás otro aviso si falla algo más, si sigue fallando dentro de '.config('errores.alerta_cada_minutos').' minutos, y cuando todo vuelva a funcionar.');
    }
}
