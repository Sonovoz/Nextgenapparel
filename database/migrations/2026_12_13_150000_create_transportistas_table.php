<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D226: Ajustes → Transportistas: la ficha de cada empresa de transporte. Cada zona de envío dice qué
 * transportista la lleva (sus tarifas son las de la zona) y el pedido enviado guarda el enlace de seguimiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transportistas', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->string('nombre', 80)->unique();
            $tabla->string('web', 200)->nullable();
            $tabla->string('url_seguimiento', 300)->nullable(); // con {numero}: https://…?ref={numero}
            $tabla->string('contacto', 120)->nullable();
            $tabla->string('telefono', 30)->nullable();
            $tabla->string('correo', 150)->nullable();
            $tabla->string('numero_cliente', 60)->nullable();
            $tabla->text('notas')->nullable();
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();
        });
        Schema::table('zonas_envio', function (Blueprint $tabla): void {
            $tabla->foreignId('transportista_id')->nullable()->after('nombre')->constrained('transportistas')->nullOnDelete();
        });
        Schema::table('pedidos', function (Blueprint $tabla): void {
            $tabla->string('url_seguimiento', 400)->nullable()->after('numero_seguimiento');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $tabla): void {
            $tabla->dropColumn('url_seguimiento');
        });
        Schema::table('zonas_envio', function (Blueprint $tabla): void {
            $tabla->dropConstrainedForeignId('transportista_id');
        });
        Schema::dropIfExists('transportistas');
    }
};
