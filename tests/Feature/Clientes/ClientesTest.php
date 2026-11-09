<?php

namespace Tests\Feature\Clientes;

use App\Dominio\Clientes\TipoCliente;
use App\Dominio\Clientes\TipoDireccion;
use App\Models\Cliente;
use App\Models\Direccion;
use App\Models\Diseno;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Datos de clientes (paso 5.1). */
class ClientesTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_correo_se_guarda_en_minusculas_y_no_se_repite_aunque_cambien_las_mayusculas(): void
    {
        $cliente = Cliente::factory()->create(['correo' => '  Ana.Garcia@Ejemplo.COM ']);
        $this->assertSame('ana.garcia@ejemplo.com', $cliente->correo);

        $this->expectException(UniqueConstraintViolationException::class);
        Cliente::factory()->create(['correo' => 'ANA.GARCIA@ejemplo.com']);
    }

    public function test_el_nif_se_guarda_normalizado(): void
    {
        $this->assertSame('B12345674', Cliente::factory()->create(['nif' => 'b-1234567.4'])->nif);
        $this->assertNull(Cliente::factory()->create(['nif' => '  '])->nif);
    }

    public function test_solo_autonomos_y_empresas_admiten_recargo_de_equivalencia(): void
    {
        $this->assertFalse(TipoCliente::Particular->admiteRecargo());
        $this->assertTrue(TipoCliente::Autonomo->admiteRecargo());
        $this->assertTrue(TipoCliente::Empresa->admiteRecargo());
    }

    public function test_los_datos_fiscales_estan_completos_con_nif_valido_y_direccion_de_facturacion(): void
    {
        $cliente = Cliente::factory()->create(['nif' => '12345678Z']);
        Direccion::factory()->for($cliente)->create(['tipo' => TipoDireccion::Envio]);
        $this->assertFalse($cliente->datosFiscalesCompletos());

        Direccion::factory()->for($cliente)->create(['tipo' => TipoDireccion::Facturacion]);
        $this->assertTrue($cliente->datosFiscalesCompletos());

        $cliente->update(['nif' => '12345678A']);
        $this->assertFalse($cliente->datosFiscalesCompletos());
    }

    public function test_al_borrar_un_cliente_se_borran_sus_direcciones_y_disenos_y_al_borrar_su_usuario_queda_como_invitado(): void
    {
        $usuario = User::factory()->create();
        $cliente = Cliente::factory()->create(['user_id' => $usuario->id]);
        $this->assertTrue($usuario->cliente->is($cliente));
        $this->assertFalse($cliente->esInvitado());

        $usuario->delete();
        $this->assertTrue($cliente->fresh()->esInvitado());

        $direccion = Direccion::factory()->for($cliente)->create();
        $diseno = Diseno::forceCreate([
            'uuid' => '11111111-1111-1111-1111-111111111111', 'cliente_id' => $cliente->id, 'nombre_original' => 'logo.png',
            'ruta' => 'x.png', 'bytes' => 1, 'sha256' => str_repeat('a', 64), 'ancho_px' => 1, 'alto_px' => 1, 'util_x' => 0,
            'util_y' => 0, 'util_ancho_px' => 1, 'util_alto_px' => 1, 'fondo' => 'transparente', 'profundidad_bits' => 8,
            'derechos_declarados_en' => now(), 'derechos_texto' => 'Declaro…',
        ]);
        $this->assertTrue($diseno->cliente->is($cliente));

        $cliente->delete();
        $this->assertModelMissing($direccion);
        $this->assertModelMissing($diseno);
    }
}
