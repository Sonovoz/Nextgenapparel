<?php

namespace App\Support;

/** Nombres legibles de los campos de la cuenta en los mensajes de error («la contraseña», no «contrasena»). */
final class CamposCuenta
{
    /** @return array<string, string> */
    public static function nombres(): array
    {
        return [
            'nombre' => mb_strtolower(__('cuenta.campo_nombre')),
            'correo' => mb_strtolower(__('cuenta.correo')),
            'contrasena' => mb_strtolower(__('cuenta.contrasena')),
            'tipo' => mb_strtolower(__('cuenta.tipo')),
        ];
    }
}
