<?php

namespace App\Dominio\Clientes;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Alta de un cliente desde la tienda (paso 5.2, D153): pide lo mínimo. Los datos
 * fiscales llegan al cerrar el pedido. Las altas de clientes no van al registro de
 * actividad del equipo, que es para usuarios del backend (D57).
 *
 * Si ya existía una ficha de invitado con ese correo, no se toca aquí: se une a la
 * cuenta cuando el correo se verifica (VincularCliente), para que nadie vea pedidos
 * ajenos registrando un correo que no es suyo.
 */
final class RegistrarCliente
{
    public function __invoke(string $nombre, string $correo, string $contrasena, TipoCliente $tipo, bool $marketing, string $idioma): User
    {
        $correo = mb_strtolower(trim($correo));

        return activity()->withoutLogs(fn () => DB::transaction(function () use ($nombre, $correo, $contrasena, $tipo, $marketing, $idioma): User {
            $usuario = User::create([
                'name' => $nombre,
                'email' => $correo,
                'password' => Hash::make($contrasena),
                'activo' => true,
            ]);

            if (! Cliente::where('correo', $correo)->exists()) {
                Cliente::create([
                    'user_id' => $usuario->id,
                    'correo' => $correo,
                    'tipo' => $tipo,
                    'nombre' => $nombre,
                    'idioma' => $idioma,
                    'marketing_consentido_en' => $marketing ? now() : null,
                    'marketing_origen' => $marketing ? 'registro' : null,
                ]);
            }

            return $usuario;
        }));
    }
}
