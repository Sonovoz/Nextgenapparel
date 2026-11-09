<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Blog (v2, D195): entradas en es/en con su SEO; se publican con fecha. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entradas_blog', function (Blueprint $table) {
            $table->id();
            $table->json('titulo');
            $table->json('slug');
            $table->json('extracto')->nullable();
            $table->json('contenido'); // Markdown
            $table->json('seo_titulo')->nullable();
            $table->json('seo_descripcion')->nullable();
            $table->dateTime('publicada_en')->nullable(); // null: borrador
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entradas_blog');
    }
};
