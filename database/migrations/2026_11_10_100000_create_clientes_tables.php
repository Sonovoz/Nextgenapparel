<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Clientes y direcciones (ficha § 5, fase 5, D153). Un cliente puede tener cuenta
| (user_id) o ser invitado (sin cuenta). Los datos fiscales se piden al cerrar el
| pedido, así que el NIF puede faltar.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique(); // público; char(36) por D148
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('correo', 255)->unique(); // siempre en minúsculas (Cliente::correo)
            $table->string('tipo', 20)->default('particular'); // particular | autonomo | empresa
            $table->string('nombre', 150); // nombre y apellidos o razón social
            $table->string('nif', 20)->nullable();
            $table->boolean('recargo_equivalencia')->default(false);
            $table->string('telefono', 30)->nullable();
            $table->string('idioma', 5)->default('es');
            $table->dateTime('marketing_consentido_en')->nullable(); // sin marcar por defecto (§ 13)
            $table->string('marketing_origen', 50)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('direcciones', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('tipo', 20); // facturacion | envio
            $table->string('nombre', 150);
            $table->string('empresa', 150)->nullable();
            $table->string('direccion', 200);
            $table->string('direccion_2', 200)->nullable();
            $table->string('codigo_postal', 10);
            $table->string('localidad', 100);
            $table->string('provincia', 100)->nullable();
            $table->char('pais', 2)->default('ES');
            $table->string('telefono', 30)->nullable();
            $table->boolean('predeterminada')->default(false);
            $table->timestamps();
            $table->index(['cliente_id', 'tipo']);
        });

        // Los diseños ya existentes son de visitantes: ninguno apunta a un cliente.
        DB::table('disenos')->whereNotNull('cliente_id')->update(['cliente_id' => null]);
        Schema::table('disenos', function (Blueprint $table) {
            // Al borrar un cliente se borran sus filas; los archivos los recoge la limpieza nocturna (D150).
            $table->foreign('cliente_id')->references('id')->on('clientes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('disenos', function (Blueprint $table) {
            $table->dropForeign(['cliente_id']);
        });
        Schema::dropIfExists('direcciones');
        Schema::dropIfExists('clientes');
    }
};
