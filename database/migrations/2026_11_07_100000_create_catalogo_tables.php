<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo (fase 2, ficha sección 5).
 *
 * - Textos traducibles en columnas JSON ({"es": "...", "en": "..."}).
 * - Dinero en céntimos, pesos en gramos y medidas en milímetros: enteros,
 *   sin redondeos (regla 7).
 * - Posición de las zonas sobre las fotos en centésimas de porcentaje
 *   (0–10000), también enteras.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('familias', function (Blueprint $table) {
            $table->id();
            $table->json('nombre');
            $table->json('slug');
            $table->json('descripcion')->nullable();
            $table->unsignedSmallInteger('plazo_base_dias')->default(2);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('tipo_conector', 20)->default('manual');
            $table->text('configuracion')->nullable(); // cifrada (claves de API)
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('colores', function (Blueprint $table) {
            $table->id();
            $table->json('nombre');
            $table->char('codigo_hex', 7);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('tallas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 10)->unique();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('zonas_impresion', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 40)->unique();
            $table->json('nombre');
            $table->string('cara', 20); // delantera | trasera
            $table->unsignedSmallInteger('ancho_max_mm');
            $table->unsignedSmallInteger('alto_max_mm');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('prendas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('familia_id')->constrained('familias')->restrictOnDelete();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->string('referencia_proveedor', 100)->nullable();
            $table->json('nombre');
            $table->json('slug');
            $table->json('descripcion')->nullable();
            $table->json('composicion')->nullable();
            $table->unsignedSmallInteger('gramaje')->nullable(); // g/m²
            $table->json('detalles')->nullable();
            $table->json('seo_titulo')->nullable();
            $table->json('seo_descripcion')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
            $table->softDeletes(); // papelera, como en WooCommerce
        });

        Schema::create('variantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prenda_id')->constrained('prendas')->cascadeOnDelete();
            $table->foreignId('color_id')->constrained('colores')->restrictOnDelete();
            $table->foreignId('talla_id')->constrained('tallas')->restrictOnDelete();
            $table->string('sku', 64)->unique();
            $table->unsignedInteger('precio_base'); // céntimos, sin IVA
            $table->unsignedInteger('coste')->default(0); // céntimos, sin IVA
            $table->unsignedInteger('peso_gramos');
            $table->integer('stock')->default(0);
            $table->unsignedInteger('stock_minimo')->default(0);
            $table->boolean('activa')->default(true);
            $table->timestamps();
            $table->unique(['prenda_id', 'color_id', 'talla_id']);
        });

        Schema::create('prenda_zona', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prenda_id')->constrained('prendas')->cascadeOnDelete();
            $table->foreignId('zona_impresion_id')->constrained('zonas_impresion')->restrictOnDelete();
            $table->unsignedInteger('precio'); // céntimos por prenda, sin IVA
            $table->unsignedInteger('coste')->default(0);
            $table->timestamps();
            $table->unique(['prenda_id', 'zona_impresion_id']);
        });

        Schema::create('medidas_talla', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prenda_id')->constrained('prendas')->cascadeOnDelete();
            $table->foreignId('talla_id')->constrained('tallas')->restrictOnDelete();
            $table->unsignedSmallInteger('largo_mm')->nullable();
            $table->unsignedSmallInteger('ancho_mm')->nullable();
            $table->unsignedSmallInteger('manga_mm')->nullable();
            $table->timestamps();
            $table->unique(['prenda_id', 'talla_id']);
        });

        Schema::create('fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prenda_id')->constrained('prendas')->cascadeOnDelete();
            $table->foreignId('color_id')->nullable()->constrained('colores')->nullOnDelete();
            $table->string('tipo', 20); // maqueta | modelo | detalle
            $table->string('cara', 20)->nullable(); // maqueta: delantera | trasera
            $table->string('ruta', 500);
            $table->string('origen', 20)->default('manual'); // manual | api
            $table->json('texto_alternativo')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
            $table->index(['prenda_id', 'tipo', 'color_id']);
        });

        // Rectángulo de cada zona de impresión sobre una foto de maqueta.
        Schema::create('foto_zona', function (Blueprint $table) {
            $table->id();
            $table->foreignId('foto_id')->constrained('fotos')->cascadeOnDelete();
            $table->foreignId('zona_impresion_id')->constrained('zonas_impresion')->cascadeOnDelete();
            $table->unsignedSmallInteger('x'); // centésimas de % (0–10000)
            $table->unsignedSmallInteger('y');
            $table->unsignedSmallInteger('ancho');
            $table->unsignedSmallInteger('alto');
            $table->timestamps();
            $table->unique(['foto_id', 'zona_impresion_id']);
        });
    }

    public function down(): void
    {
        foreach (['foto_zona', 'fotos', 'medidas_talla', 'prenda_zona', 'variantes', 'prendas', 'zonas_impresion', 'tallas', 'colores', 'proveedores', 'familias'] as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }
};
