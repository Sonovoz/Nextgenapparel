<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D216: la validez del presupuesto se cuenta en horas (48 por defecto), no en días: valido_hasta pasa a
 * fecha y hora. Los que ya había valen hasta el final de su día, como hasta ahora.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presupuestos', function (Blueprint $tabla): void {
            $tabla->dateTime('valido_hasta')->change();
        });
        foreach (DB::table('presupuestos')->get(['id', 'valido_hasta']) as $p) {
            DB::table('presupuestos')->where('id', $p->id)->update(['valido_hasta' => substr((string) $p->valido_hasta, 0, 10).' 23:59:59']);
        }
    }

    public function down(): void
    {
        Schema::table('presupuestos', function (Blueprint $tabla): void {
            $tabla->date('valido_hasta')->change();
        });
    }
};
