<?php

namespace App\Dominio\Asistente;

use App\Models\Ajuste;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Llamada a OpenAI (Chat Completions con herramientas) por HTTP, sin SDK (v2, D194). La clave sale del .env y
 * el modelo de Ajustes → IA. Cualquier fallo es IaNoDisponible.
 */
class ClienteOpenAi
{
    /**
     * @param  list<array<string, mixed>>  $mensajes
     * @param  list<array<string, mixed>>  $herramientas
     * @return array{mensaje: array<string, mixed>, entrada: int, salida: int}
     */
    public function chat(array $mensajes, array $herramientas = [], bool $json = false): array
    {
        $clave = (string) config('ia.openai_key');
        if ($clave === '') {
            throw new IaNoDisponible('Falta OPENAI_API_KEY en el .env.');
        }
        try {
            $respuesta = Http::withToken($clave)->acceptJson()->timeout(30)->post(rtrim((string) config('ia.openai_url'), '/').'/chat/completions', array_filter([
                'model' => (string) Ajuste::obtener('ia.modelo'),
                'messages' => $mensajes,
                'tools' => $herramientas === [] ? null : $herramientas,
                'response_format' => $json ? ['type' => 'json_object'] : null,
                'temperature' => 0.3,
            ], fn ($v) => $v !== null));
        } catch (ConnectionException $e) {
            throw new IaNoDisponible('OpenAI no responde: '.$e->getMessage());
        }
        if (! $respuesta->successful() || ! is_array($respuesta->json('choices.0.message'))) {
            throw new IaNoDisponible('OpenAI ha devuelto un error ('.$respuesta->status().').');
        }

        return ['mensaje' => (array) $respuesta->json('choices.0.message'), 'entrada' => (int) $respuesta->json('usage.prompt_tokens'), 'salida' => (int) $respuesta->json('usage.completion_tokens')];
    }
}
