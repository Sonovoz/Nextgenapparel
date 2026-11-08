<?php

namespace Tests\Feature\Catalogo;

use App\Filament\Resources\Catalogo\Prendas\Pages\ListPrendas;
use App\Models\Prenda;
use Database\Seeders\EjemplosCatalogoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Buscar prendas por nombre en el backend, en cualquier idioma. */
class BuscarPrendasTest extends TestCase
{
    use RefreshDatabase;

    public function test_busca_prendas_por_su_nombre_en_espanol_o_en_ingles(): void
    {
        $this->seed(EjemplosCatalogoSeeder::class);
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->usuarioConRol());
        $polo = Prenda::where('slug->es', 'polo-classic')->sole();
        $camiseta = Prenda::where('slug->es', 'camiseta-essential')->sole();

        Livewire::test(ListPrendas::class)
            ->searchTable('Polo')->assertCanSeeTableRecords([$polo])->assertCanNotSeeTableRecords([$camiseta])
            ->searchTable('T-shirt')->assertCanSeeTableRecords([$camiseta])->assertCanNotSeeTableRecords([$polo]);
    }
}
