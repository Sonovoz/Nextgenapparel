<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| El mismo archivo una sola vez por cliente, como ya pasa por sesión (D148).
| Con cliente_id nulo (visitantes) el índice no restringe nada.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disenos', function (Blueprint $table) {
            $table->unique(['cliente_id', 'sha256']);
        });
    }

    public function down(): void
    {
        Schema::table('disenos', function (Blueprint $table) {
            $table->dropUnique(['cliente_id', 'sha256']);
        });
    }
};
