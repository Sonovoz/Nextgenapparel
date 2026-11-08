<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3: motor de precios. Importes en céntimos (enteros) y porcentajes en
 * centésimas (2100 = 21 %). Vigencias en DATETIME (D59).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clases_impuesto', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 40)->unique();
            $table->json('nombre');
            $table->unsignedSmallInteger('tipo_iva');           // 2100 = 21 %
            $table->unsignedSmallInteger('recargo')->default(0); // recargo de equivalencia, 520 = 5,2 %
            $table->boolean('por_defecto')->default(false);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        // Clases de partida (España, editables en Ajustes → Impuestos). La general
        // conserva el IVA provisional que se hubiera configurado en la fase 2 (D75).
        $general = DB::table('ajustes')->where('clave', 'impuestos.iva_general')->value('valor');
        $ahora = now();
        DB::table('clases_impuesto')->insert([
            ['clave' => 'general', 'nombre' => json_encode(['es' => 'General', 'en' => 'Standard']), 'tipo_iva' => $general !== null ? (int) json_decode($general) : 2100, 'recargo' => 520, 'por_defecto' => true, 'orden' => 0, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['clave' => 'reducido', 'nombre' => json_encode(['es' => 'Reducido', 'en' => 'Reduced']), 'tipo_iva' => 1000, 'recargo' => 140, 'por_defecto' => false, 'orden' => 1, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['clave' => 'superreducido', 'nombre' => json_encode(['es' => 'Superreducido', 'en' => 'Super-reduced']), 'tipo_iva' => 400, 'recargo' => 50, 'por_defecto' => false, 'orden' => 2, 'created_at' => $ahora, 'updated_at' => $ahora],
        ]);

        Schema::table('prendas', function (Blueprint $table) {
            // Null: la clase por defecto (como «Estándar» en WooCommerce).
            $table->foreignId('clase_impuesto_id')->nullable()->after('proveedor_id')->constrained('clases_impuesto')->restrictOnDelete();
        });

        Schema::create('reglas_minimo', function (Blueprint $table) {
            $table->id();
            $table->json('nombre');
            $table->string('tipo', 30);                 // unidades_total | unidades_por_talla | importe_minimo
            $table->unsignedInteger('valor');           // unidades o céntimos
            $table->string('ambito', 20)->default('catalogo'); // catalogo | familia | prenda
            $table->unsignedBigInteger('ambito_id')->nullable();
            $table->dateTime('desde')->nullable();
            $table->dateTime('hasta')->nullable();
            $table->smallInteger('prioridad')->default(0);
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('reglas_precio', function (Blueprint $table) {
            $table->id();
            $table->json('nombre');
            $table->string('tipo', 30);                 // tramo_cantidad | combinada
            $table->json('condiciones');                // validado por App\Dominio\Precios\Reglas
            $table->string('efecto', 20);               // porcentaje | importe
            $table->unsignedInteger('valor');           // centésimas de % o céntimos
            $table->string('aplica_a', 20)->default('todo'); // prendas | impresion | todo
            $table->string('ambito', 20)->default('catalogo');
            $table->unsignedBigInteger('ambito_id')->nullable();
            $table->dateTime('desde')->nullable();
            $table->dateTime('hasta')->nullable();
            $table->smallInteger('prioridad')->default(0);
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('cupones', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('tipo', 20);                 // porcentaje | importe
            $table->unsignedInteger('valor');
            $table->unsignedInteger('minimo_importe')->nullable(); // céntimos sin IVA
            $table->unsignedInteger('usos_maximos')->nullable();
            $table->unsignedInteger('usos')->default(0);
            $table->boolean('un_uso_por_cliente')->default(true);
            $table->dateTime('desde')->nullable();
            $table->dateTime('hasta')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('zonas_envio', function (Blueprint $table) {
            $table->id();
            $table->json('nombre');
            $table->json('paises');                     // ["ES"]
            $table->json('prefijos_cp')->nullable();    // ["07"]: Baleares; null = todo el país
            $table->string('metodo', 20);               // por_kilo | por_caja
            $table->unsignedInteger('precio_kg_extra')->nullable();
            $table->unsignedInteger('precio_caja')->nullable();
            $table->unsignedInteger('gratis_desde')->nullable(); // céntimos sin IVA
            $table->unsignedTinyInteger('plazo_dias')->default(2);
            $table->smallInteger('prioridad')->default(0);
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('tarifas_envio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zona_envio_id')->constrained('zonas_envio')->cascadeOnDelete();
            $table->unsignedInteger('hasta_gramos');
            $table->unsignedInteger('precio');
            $table->timestamps();
            $table->unique(['zona_envio_id', 'hasta_gramos']);
        });

        Schema::create('cajas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->unsignedSmallInteger('capacidad_prendas');
            $table->unsignedInteger('peso_max_gramos');
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('packs', function (Blueprint $table) {
            $table->id();
            $table->json('nombre');
            $table->json('slug');
            $table->json('descripcion')->nullable();
            $table->unsignedInteger('precio');          // cerrado, sin IVA
            $table->foreignId('clase_impuesto_id')->nullable()->constrained('clases_impuesto')->restrictOnDelete();
            $table->string('reparto', 20)->default('fijo'); // fijo | libre
            $table->dateTime('desde')->nullable();
            $table->dateTime('hasta')->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('imagen')->nullable();
            $table->json('seo_titulo')->nullable();
            $table->json('seo_descripcion')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lineas_pack', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pack_id')->constrained('packs')->cascadeOnDelete();
            $table->foreignId('prenda_id')->constrained('prendas')->restrictOnDelete();
            $table->unsignedSmallInteger('unidades');
            $table->json('colores');                    // ids permitidos (fijo: uno)
            $table->json('reparto_tallas')->nullable(); // fijo: {talla_id: unidades}
            $table->json('tallas_permitidas')->nullable(); // libre: [talla_id]
            $table->timestamps();
        });

        Schema::create('pack_zona', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pack_id')->constrained('packs')->cascadeOnDelete();
            $table->foreignId('zona_impresion_id')->constrained('zonas_impresion')->restrictOnDelete();
            $table->unique(['pack_id', 'zona_impresion_id']);
        });
    }

    public function down(): void
    {
        foreach (['pack_zona', 'lineas_pack', 'packs', 'cajas', 'tarifas_envio', 'zonas_envio', 'cupones', 'reglas_precio', 'reglas_minimo'] as $tabla) {
            Schema::dropIfExists($tabla);
        }
        Schema::table('prendas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clase_impuesto_id');
        });
        Schema::dropIfExists('clases_impuesto');
    }
};
