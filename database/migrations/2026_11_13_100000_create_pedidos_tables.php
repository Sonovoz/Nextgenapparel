<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedidos (paso 6.1, D169). El pedido es una copia congelada de lo que el cliente
 * confirmó: sus datos y direcciones, cada línea con sus nombres y tallas, cada
 * impresión con su archivo copiado al disco privado «pedidos», los importes del motor
 * en céntimos y su desglose. Nada se recalcula después. Fechas con dateTime (D59).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Numeración correlativa sin huecos por carreras: se lee y se suma bajo bloqueo.
        Schema::create('contadores', function (Blueprint $table) {
            $table->string('clave', 30)->primary();
            $table->unsignedBigInteger('valor')->default(0);
        });

        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique(); // público; char(36) por D148
            $table->string('numero', 30)->unique();
            // Sin ficha mientras un invitado no confirma su correo (D169): nadie puede
            // colgar pedidos en la cuenta de otro con solo escribir su correo.
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('estado', 30)->default('pendiente_pago')->index();
            $table->string('idioma', 5);
            $table->string('correo', 255)->index();
            $table->dateTime('correo_confirmado_en')->nullable();
            $table->string('tipo_cliente', 20);
            $table->string('nombre_fiscal', 150);
            $table->string('nif', 20)->nullable();
            $table->boolean('recargo_equivalencia')->default(false);
            $table->json('direccion_envio');
            $table->json('direccion_facturacion');
            $table->string('cupon', 50)->nullable();
            // Importes en céntimos (regla 7), tal como los dio el motor al confirmar.
            $table->unsignedInteger('bruto');
            $table->unsignedInteger('descuentos');
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('envio');
            $table->unsignedInteger('base_imponible');
            $table->unsignedInteger('impuestos');
            $table->unsignedInteger('total');
            $table->json('desglose'); // cada paso del motor (Resultado::aArray)
            $table->dateTime('condiciones_aceptadas_en');
            $table->string('condiciones_texto', 300); // lo que aceptó, en su idioma
            $table->string('ip', 45)->nullable();
            $table->date('fecha_estimada_entrega')->nullable();
            $table->string('transportista', 80)->nullable();
            $table->string('numero_seguimiento', 80)->nullable();
            $table->timestamps();
        });

        Schema::create('lineas_pedido', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->unsignedInteger('orden')->default(0);
            $table->string('tipo', 10); // prenda | pack
            // Referencias al catálogo (si se borra algo, el pedido conserva su copia).
            $table->foreignId('prenda_id')->nullable()->constrained('prendas')->nullOnDelete();
            $table->foreignId('color_id')->nullable()->constrained('colores')->nullOnDelete();
            $table->foreignId('pack_id')->nullable()->constrained('packs')->nullOnDelete();
            $table->json('nombre'); // es/en
            $table->json('color_nombre')->nullable();
            $table->string('color_hex', 7)->nullable();
            $table->json('tallas')->nullable(); // [{talla_id, nombre, unidades}]
            $table->json('elecciones')->nullable(); // pack: la copia de lo que repartió
            $table->unsignedInteger('unidades');
            $table->unsignedInteger('prendas');
            $table->unsignedInteger('impresion');
            $table->unsignedInteger('descuento');
            $table->unsignedInteger('cupon');
            $table->unsignedInteger('neto');
            $table->unsignedSmallInteger('tipo_iva');
            $table->unsignedSmallInteger('recargo');
            $table->timestamps();
        });

        Schema::create('impresiones_pedido', function (Blueprint $table) {
            $table->id();
            $table->foreignId('linea_pedido_id')->constrained('lineas_pedido')->cascadeOnDelete();
            $table->foreignId('zona_impresion_id')->nullable()->constrained('zonas_impresion')->nullOnDelete();
            $table->json('zona_nombre');
            $table->string('cara', 10)->nullable();
            // Un logo (su archivo copiado al pedido) o un texto de «Escribir mi marca».
            $table->foreignId('diseno_id')->nullable()->constrained('disenos')->nullOnDelete();
            $table->string('archivo', 160)->nullable();
            $table->string('miniatura', 160)->nullable();
            $table->string('nombre_original', 150)->nullable();
            $table->char('sha256', 64)->nullable();
            $table->unsignedSmallInteger('util_ancho_px')->nullable();
            $table->unsignedSmallInteger('util_alto_px')->nullable();
            $table->unsignedSmallInteger('ppp')->nullable();
            $table->string('nivel_calidad', 20)->nullable();
            $table->string('texto', 60)->nullable();
            $table->unsignedTinyInteger('fuente')->nullable();
            $table->string('tinta', 10)->nullable();
            $table->unsignedSmallInteger('ancho_mm');
            $table->unsignedSmallInteger('alto_mm');
            $table->unsignedSmallInteger('desde_cuello_mm');
            $table->smallInteger('desde_centro_mm');
            $table->string('estado_revision', 20)->default('pendiente'); // la revisión humana, en el paso 6.3
            $table->timestamps();
        });

        Schema::create('historial_estados_pedido', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->string('estado', 30);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // null: la tienda o el sistema
            $table->text('nota')->nullable();
            $table->boolean('notificado_cliente')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_estados_pedido');
        Schema::dropIfExists('impresiones_pedido');
        Schema::dropIfExists('lineas_pedido');
        Schema::dropIfExists('pedidos');
        Schema::dropIfExists('contadores');
    }
};
