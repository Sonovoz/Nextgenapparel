<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Carrito persistente (paso 5.5, ficha § 5): lo que el cliente quiere comprar, nunca
| sus precios (los calcula el motor cada vez, regla 3). Uno por cliente con cuenta o,
| para un invitado, por el token de su cookie (solo se guarda su hash). Las impresiones
| tienen la misma forma que tendrán las del pedido (fase 6): zona, diseño, tamaño y
| posición en mm desde el cuello y desde el centro (D99). Prendas y packs nunca se borran
| del todo (papelera); colores y zonas en uso en un carrito no se pueden borrar (CatalogoPolicy).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carritos', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('cliente_id')->nullable()->unique()->constrained('clientes')->cascadeOnDelete();
            $table->char('token', 64)->nullable()->unique(); // hash del token de la cookie del invitado
            $table->string('cupon', 50)->nullable();
            $table->timestamps(); // updated_at: último cambio, para la retención
        });

        Schema::create('lineas_carrito', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('carrito_id')->constrained('carritos')->cascadeOnDelete();
            $table->string('tipo', 10); // prenda | pack
            $table->foreignId('prenda_id')->nullable()->constrained('prendas')->restrictOnDelete();
            $table->foreignId('color_id')->nullable()->constrained('colores')->restrictOnDelete();
            $table->json('tallas')->nullable(); // talla_id => unidades
            $table->foreignId('pack_id')->nullable()->constrained('packs')->restrictOnDelete();
            $table->json('elecciones')->nullable(); // pack: linea_pack_id => {color, tallas}
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('impresiones_carrito', function (Blueprint $table) {
            $table->id();
            $table->foreignId('linea_carrito_id')->constrained('lineas_carrito')->cascadeOnDelete();
            $table->foreignId('zona_impresion_id')->constrained('zonas_impresion')->restrictOnDelete();
            // El diseño del cliente; si se borra, la línea deja de estar disponible hasta que ponga otro.
            $table->foreignId('diseno_id')->nullable()->constrained('disenos')->nullOnDelete();
            $table->unsignedSmallInteger('ancho_mm');
            $table->unsignedSmallInteger('alto_mm');
            $table->unsignedSmallInteger('desde_cuello_mm');
            $table->smallInteger('desde_centro_mm'); // negativo: a la izquierda de quien la lleva
            $table->timestamps();
            $table->unique(['linea_carrito_id', 'zona_impresion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impresiones_carrito');
        Schema::dropIfExists('lineas_carrito');
        Schema::dropIfExists('carritos');
    }
};
