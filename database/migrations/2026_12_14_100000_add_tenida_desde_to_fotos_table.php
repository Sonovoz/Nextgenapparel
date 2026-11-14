<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// D251: maquetas generadas tiñendo la del blanco. Se marcan para poder rehacerlas sin pisar nunca una foto real.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fotos', function (Blueprint $table) {
            $table->foreignId('tenida_desde_id')->nullable()->after('origen')->constrained('fotos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fotos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenida_desde_id');
        });
    }
};
