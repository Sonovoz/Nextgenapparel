<?php

namespace Tests\Feature\Precios;

use App\Dominio\Pedidos\CambiarEstado;
use App\Dominio\Pedidos\EstadoPedido;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Resources\Precios\Pages\ManageTransportistas;
use App\Filament\Resources\Precios\TransportistaResource;
use App\Models\Pedido;
use App\Models\Transportista;
use App\Models\User;
use App\Models\ZonaEnvio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** D226: fichas de transportistas, sus zonas y tarifas, y el enlace de seguimiento del pedido enviado. */
class TransportistasTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_ficha_se_crea_en_el_backend_y_ensena_sus_zonas_y_tarifas(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::Superadministrador));

        Livewire::test(ManageTransportistas::class)->callAction('create', ['nombre' => 'GLS', 'url_seguimiento' => 'https://gls.example/seguimiento'])
            ->assertHasFormErrors(['url_seguimiento']); // sin {numero}
        Livewire::test(ManageTransportistas::class)->callAction('create', [
            'nombre' => 'GLS', 'contacto' => 'Ana', 'telefono' => '910000000', 'url_seguimiento' => 'https://gls.example/seguimiento?ref={numero}', 'activo' => true,
        ])->assertHasNoFormErrors();

        $gls = Transportista::sole();
        $zona = ZonaEnvio::create(['nombre' => ['es' => 'Península'], 'transportista_id' => $gls->id, 'paises' => ['ES'], 'metodo' => 'por_kilo', 'precio_kg_extra' => 80, 'plazo_dias' => 2]);
        $zona->tarifas()->create(['hasta_gramos' => 5000, 'precio' => 690]);

        $ficha = (string) TransportistaResource::tarifas($gls);
        $this->assertStringContainsString('Península', $ficha);
        $this->assertStringContainsString('hasta 5 kg: 6,90 €', $ficha);
        $this->assertStringContainsString('kilo extra: 0,80 €', $ficha);
        $this->assertSame('https://gls.example/seguimiento?ref=AB%2012', $gls->enlace('AB 12'));
    }

    public function test_al_enviar_el_pedido_guarda_el_enlace_de_seguimiento_del_transportista(): void
    {
        Transportista::create(['nombre' => 'GLS', 'url_seguimiento' => 'https://gls.example/s?ref={numero}']);
        $pedido = Pedido::factory()->create(['estado' => EstadoPedido::EnProduccion]);

        app(CambiarEstado::class)($pedido, EstadoPedido::Enviado, User::factory()->create(), null, ['transportista' => 'GLS', 'numero_seguimiento' => 'X123']);
        $this->assertSame('https://gls.example/s?ref=X123', $pedido->fresh()->url_seguimiento);

        // Un transportista sin ficha (escrito a mano) no tiene enlace.
        $otro = Pedido::factory()->create(['estado' => EstadoPedido::EnProduccion]);
        app(CambiarEstado::class)($otro, EstadoPedido::Enviado, User::factory()->create(), null, ['transportista' => 'Mensajero', 'numero_seguimiento' => 'Z9']);
        $this->assertNull($otro->fresh()->url_seguimiento);
    }
}
