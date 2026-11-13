<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D235: registro de cada correo que manda la tienda (a quién, qué, cuándo y qué le pasó), para encontrar
 * por qué un correo no llega: en cola sin procesar, fallo del SMTP o no enviado (correo sin confirmar).
 * Se guarda 90 días.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registro_correos', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->char('notificacion_id', 36)->nullable()->index();
            $tabla->string('tipo', 80);
            $tabla->string('destinatario', 255)->index();
            $tabla->string('asunto', 255)->nullable();
            $tabla->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
            $tabla->string('estado', 20)->index(); // en_cola | enviando | enviado | reintentando | fallido | no_enviado
            $tabla->unsignedSmallInteger('intentos')->default(0);
            $tabla->text('error')->nullable();
            $tabla->dateTime('enviado_en')->nullable();
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registro_correos');
    }
};
