<?php

namespace Tests\Feature\Precios;

use App\Dominio\Precios\Motor\CalculoEnvio;
use App\Dominio\Precios\Motor\Destino;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Resources\Precios\Pages\ManageZonaEnvio;
use App\Models\Caja;
use App\Models\ZonaEnvio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** D225: el envío dice en el backend por qué no se puede cobrar (al cliente, «Todavía no enviamos a ese destino»). */
class CalculoEnvioTest extends TestCase
{
    use RefreshDatabase;

    public function test_cada_motivo_por_el_que_no_se_puede_enviar(): void
    {
        $madrid = new Destino('ES', '28001');
        $this->assertSame(CalculoEnvio::SIN_ZONA, CalculoEnvio::para($madrid, 1000, 1, 0)['motivo']);

        $peninsula = ZonaEnvio::create(['nombre' => ['es' => 'Península'], 'paises' => ['ES'], 'metodo' => 'por_kilo', 'plazo_dias' => 2]);
        $this->assertSame(CalculoEnvio::SIN_TARIFAS, CalculoEnvio::para($madrid, 1000, 1, 0)['motivo']);
        $this->assertSame(['Faltan las tarifas por peso.'], CalculoEnvio::problemas($peninsula));

        // Un tramo hasta 5 kg sin precio por kilo extra: un pedido de 12 kg no se puede enviar.
        $peninsula->tarifas()->create(['hasta_gramos' => 5000, 'precio' => 690]);
        $this->assertSame(690, CalculoEnvio::para($madrid, 4000, 20, 0)['envio']['importe']);
        $this->assertSame(CalculoEnvio::PESO_FUERA, CalculoEnvio::para($madrid, 12000, 60, 0)['motivo']);
        $this->assertStringContainsString('más de 5 kg', CalculoEnvio::problemas($peninsula->fresh())[0]);

        $peninsula->update(['precio_kg_extra' => 80]);
        $this->assertSame(690 + 7 * 80, CalculoEnvio::para($madrid, 12000, 60, 0)['envio']['importe']);
        $this->assertSame([], CalculoEnvio::problemas($peninsula->fresh()));

        // Por caja: sin caja activa y sin precio por caja.
        $baleares = ZonaEnvio::create(['nombre' => ['es' => 'Baleares'], 'paises' => ['ES'], 'prefijos_cp' => ['07'], 'metodo' => 'por_caja', 'plazo_dias' => 4]);
        $palma = new Destino('ES', '07001');
        $this->assertSame(CalculoEnvio::SIN_CAJA, CalculoEnvio::para($palma, 1000, 10, 0)['motivo']);
        Caja::create(['nombre' => 'Caja grande', 'capacidad_prendas' => 50, 'peso_max_gramos' => 20000]);
        $this->assertSame(CalculoEnvio::SIN_PRECIO_CAJA, CalculoEnvio::para($palma, 1000, 10, 0)['motivo']);
        $this->assertSame(['Falta el precio por caja.'], CalculoEnvio::problemas($baleares));
    }

    public function test_el_backend_prueba_un_envio_y_marca_las_zonas_incompletas(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::Superadministrador));
        $zona = ZonaEnvio::create(['nombre' => ['es' => 'Península'], 'paises' => ['ES'], 'metodo' => 'por_kilo', 'plazo_dias' => 2]);
        $zona->tarifas()->create(['hasta_gramos' => 5000, 'precio' => 690]);

        Livewire::test(ManageZonaEnvio::class)
            ->assertSee('Los pedidos de más de 5 kg no se pueden enviar')
            ->callAction('probar', ['pais' => 'ES', 'cp' => '28001', 'kilos' => '12', 'unidades' => '60'])
            ->assertNotified('No se puede enviar');

        Livewire::test(ManageZonaEnvio::class)
            ->callAction('probar', ['pais' => 'ES', 'cp' => '28001', 'kilos' => '3', 'unidades' => '10'])
            ->assertNotified('Envío: 6,90 € sin IVA');
    }

    /** D232: el último envío sin precio (país, CP, peso, prendas y motivo) se ve en Ajustes → Envío. */
    public function test_el_ultimo_envio_sin_precio_se_ve_en_el_backend(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::Superadministrador));
        $zona = ZonaEnvio::create(['nombre' => ['es' => 'Península'], 'paises' => ['ES'], 'metodo' => 'por_kilo', 'plazo_dias' => 2]);
        $zona->tarifas()->create(['hasta_gramos' => 5000, 'precio' => 690]);
        $calculo = CalculoEnvio::para(new Destino('ES', '29300'), 12400, 60, 0);

        CalculoEnvio::apuntarFallo(new Destino('ES', '29300'), 12400, 60, (string) $calculo['motivo'], $calculo['zona']);

        Livewire::test(ManageZonaEnvio::class)->assertSee('Último envío sin precio')->assertSee('ES 29300, 12,4 kg, 60 prendas')
            ->assertSee('pesa más que el último tramo');
    }
}
