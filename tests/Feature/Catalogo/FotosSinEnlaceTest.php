<?php

namespace Tests\Feature\Catalogo;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Las fotos del catálogo se ven aunque falte el enlace public/storage; los archivos privados nunca. */
class FotosSinEnlaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sirve_las_fotos_publicas_sin_enlace_y_nunca_las_privadas(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('public')->put('catalogo/1/foto.png', 'png');
        Storage::disk('local')->put('acceso-temporal.txt', 'CLAVE: secreta');

        $this->get('/storage/catalogo/1/foto.png')->assertOk();
        $this->get('/storage/acceso-temporal.txt')->assertNotFound();
        $this->get('/storage/../private/acceso-temporal.txt')->assertNotFound();
    }
}
