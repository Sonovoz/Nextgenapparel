<?php

namespace Tests\Feature\Sistema;

use App\Dominio\Sistema\Acceso\Rol;
use App\Dominio\Sistema\Actualizaciones;
use App\Filament\Pages\Actualizaciones as Pagina;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D236: cada actualización numerada a partir del historial; la instalada se ve en el backend. */
class ActualizacionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_numeran_desde_la_mas_antigua_aunque_falte_la_barra_final(): void
    {
        $lista = Actualizaciones::leer(<<<'MD'
            # Historial
            ## Diciembre de 2026
            | Fecha | Entrega | PR / rama | Decisión |
            | --- | --- | --- | --- |
            | 02/12 | **Tercera** cosa. | `rama-3` | D3
            ## Noviembre de 2026
            | 13/11 | **Segunda** cosa. | `rama-2` | — |
            | 12/11 | Primera cosa. | #1 | D1 |
            MD);

        $this->assertSame([3, 2, 1], array_column($lista, 'numero'));
        $this->assertSame(['02/12/2026', 'Tercera cosa.', 'rama-3', 'D3'], [$lista[0]['fecha'], $lista[0]['texto'], $lista[0]['rama'], $lista[0]['decision']]);
        $this->assertSame('12/11/2026', $lista[2]['fecha']);
    }

    public function test_el_backend_ensena_la_actualizacion_instalada(): void
    {
        $actual = Actualizaciones::actual();
        $this->assertNotNull($actual);
        $this->assertSame(count(Actualizaciones::todas()), $actual['numero']);

        $this->actingAs($this->usuarioConRol(Rol::Superadministrador));
        $this->get('/admin')->assertOk()->assertSee('Actualización nº '.$actual['numero']);
        $this->get(Pagina::getUrl())->assertOk()->assertSee('instalada la nº '.$actual['numero']);
        auth()->logout();
        $this->get('/admin/login')->assertOk()->assertDontSee('Actualización nº');
    }
}
