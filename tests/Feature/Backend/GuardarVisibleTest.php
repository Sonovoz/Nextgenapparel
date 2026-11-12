<?php

namespace Tests\Feature\Backend;

use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Pages\AjustesGenerales;
use App\Filament\Resources\Catalogo\Prendas\Pages\EditPrenda;
use App\Models\Familia;
use App\Models\Prenda;
use Filament\Facades\Filament;
use Filament\Pages\BasePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** «Guardar cambios» siempre a la vista y aviso de que el cambio ya está en la tienda. */
class GuardarVisibleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->usuarioConRol(Rol::Administrador));
    }

    public function test_en_las_fichas_el_boton_de_guardar_va_fijo_abajo(): void
    {
        $this->assertTrue(BasePage::$formActionsAreSticky);
    }

    public function test_al_guardar_una_prenda_avisa_y_enlaza_a_la_tienda(): void
    {
        $prenda = Prenda::factory()->for(Familia::factory())->create(['activa' => true]);

        Livewire::test(EditPrenda::class, ['record' => $prenda->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Cambios guardados');
    }

    public function test_los_ajustes_tienen_el_boton_de_guardar_tambien_abajo(): void
    {
        $this->get(AjustesGenerales::getUrl())->assertOk()
            ->assertSee("mountAction('guardar')", false);
    }
}
