<?php

namespace Tests\Feature\Catalogo;

use App\Dominio\Catalogo\Cara;
use App\Dominio\Catalogo\DatoCatalogoInvalido;
use App\Dominio\Catalogo\TipoFoto;
use App\Models\Color;
use App\Models\Familia;
use App\Models\Foto;
use App\Models\FotoZona;
use App\Models\MedidaTalla;
use App\Models\Prenda;
use App\Models\Proveedor;
use App\Models\Talla;
use App\Models\Variante;
use App\Models\ZonaImpresion;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ModeloCatalogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_prenda_completa_con_variaciones_zonas_medidas_y_fotos(): void
    {
        $prenda = Prenda::factory()->create();
        $negro = Color::factory()->create(['codigo_hex' => '#1c1c1f']);
        [$m, $l] = [Talla::factory()->create(['nombre' => 'M']), Talla::factory()->create(['nombre' => 'L'])];
        $frontal = ZonaImpresion::factory()->create();

        Variante::factory()->for($prenda)->for($negro)->for($m)->create(['precio_base' => 450]);
        Variante::factory()->for($prenda)->for($negro)->for($l)->create(['precio_base' => 400]);
        $prenda->zonas()->attach($frontal, ['precio' => 300, 'coste' => 120]);
        MedidaTalla::create(['prenda_id' => $prenda->id, 'talla_id' => $m->id, 'largo_mm' => 720, 'ancho_mm' => 520]);
        $foto = Foto::factory()->for($prenda)->for($negro)->create();
        FotoZona::create(['foto_id' => $foto->id, 'zona_impresion_id' => $frontal->id, 'x' => 3200, 'y' => 2700, 'ancho' => 3600, 'alto' => 4300]);

        $prenda->refresh();
        $this->assertCount(2, $prenda->variantes);
        $this->assertSame(300, $prenda->zonas->first()->pivot->precio);
        $this->assertSame(720, $prenda->medidas->first()->largo_mm);
        $this->assertSame('#1C1C1F', $negro->fresh()->codigo_hex);
        $this->assertSame(3600, $prenda->fotos->first()->zonas->first()->ancho);
    }

    public function test_el_precio_desde_es_el_mas_bajo_de_las_variaciones_activas(): void
    {
        $prenda = Prenda::factory()->create();
        Variante::factory()->for($prenda)->create(['precio_base' => 450]);
        Variante::factory()->for($prenda)->create(['precio_base' => 350, 'activa' => false]);
        Variante::factory()->for($prenda)->create(['precio_base' => 400]);

        $this->assertSame(400, $prenda->precioDesde());
        $this->assertNull(Prenda::factory()->create()->precioDesde());
    }

    public function test_los_importes_se_guardan_en_centimos_enteros(): void
    {
        $variante = Variante::factory()->create(['precio_base' => 1999]);

        $this->assertSame(1999, DB::table('variantes')->where('id', $variante->id)->value('precio_base'));
        $this->assertIsInt($variante->fresh()->precio_base);
    }

    public function test_no_se_repite_una_combinacion_color_talla_en_la_misma_prenda(): void
    {
        $variante = Variante::factory()->create();

        $this->expectException(QueryException::class);
        Variante::factory()->create([
            'prenda_id' => $variante->prenda_id, 'color_id' => $variante->color_id, 'talla_id' => $variante->talla_id,
        ]);
    }

    public function test_el_sku_es_unico(): void
    {
        Variante::factory()->create(['sku' => 'CAM-ESSENTIAL-NEGRO-M']);

        $this->expectException(QueryException::class);
        Variante::factory()->create(['sku' => 'CAM-ESSENTIAL-NEGRO-M']);
    }

    public function test_los_textos_se_traducen_y_si_falta_el_ingles_se_usa_el_espanol(): void
    {
        $prenda = Prenda::factory()->create(['descripcion' => ['es' => 'Algodón peinado']]);

        App::setLocale('en');
        $this->assertSame('Essential T-shirt', $prenda->nombre);
        $this->assertSame('Algodón peinado', $prenda->descripcion);

        App::setLocale('es');
        $this->assertSame('Camiseta Essential', $prenda->nombre);
    }

    public function test_el_color_debe_tener_formato_hexadecimal(): void
    {
        $this->expectException(DatoCatalogoInvalido::class);

        Color::factory()->create(['codigo_hex' => 'negro']);
    }

    public function test_una_maqueta_necesita_color_y_cara_pero_una_foto_de_galeria_no(): void
    {
        Foto::factory()->create(['tipo' => TipoFoto::Modelo, 'color_id' => null, 'cara' => null]);

        $this->expectException(DatoCatalogoInvalido::class);
        Foto::factory()->create(['tipo' => TipoFoto::Maqueta, 'cara' => null]);
    }

    #[DataProvider('rectangulosFuera')]
    public function test_la_zona_debe_quedar_dentro_de_la_foto(int $x, int $y, int $ancho, int $alto): void
    {
        $foto = Foto::factory()->create();

        $this->expectException(DatoCatalogoInvalido::class);
        FotoZona::create(['foto_id' => $foto->id, 'zona_impresion_id' => ZonaImpresion::factory()->create()->id, 'x' => $x, 'y' => $y, 'ancho' => $ancho, 'alto' => $alto]);
    }

    public static function rectangulosFuera(): array
    {
        return [
            'se sale por la derecha' => [8000, 1000, 3000, 1000],
            'se sale por abajo' => [1000, 9000, 1000, 2000],
            'sin ancho' => [1000, 1000, 0, 1000],
        ];
    }

    public function test_una_prenda_borrada_va_a_la_papelera_y_se_recupera_con_sus_variaciones(): void
    {
        $variante = Variante::factory()->create();
        $prenda = $variante->prenda;

        $prenda->delete();
        $this->assertNull(Prenda::find($prenda->id));
        $this->assertNotNull(Variante::find($variante->id));

        $prenda->restore();
        $this->assertCount(1, $prenda->fresh()->variantes);
    }

    public function test_no_se_puede_borrar_una_familia_con_prendas(): void
    {
        $familia = Familia::factory()->create();
        Prenda::factory()->for($familia)->create();

        $this->expectException(QueryException::class);
        $familia->delete();
    }

    public function test_las_claves_de_api_del_proveedor_se_guardan_cifradas(): void
    {
        $proveedor = Proveedor::factory()->create(['configuracion' => ['clave_api' => 'secreta-123']]);

        $this->assertStringNotContainsString('secreta-123', (string) DB::table('proveedores')->value('configuracion'));
        $this->assertSame('secreta-123', $proveedor->fresh()->configuracion['clave_api']);
    }

    public function test_las_zonas_tienen_cara(): void
    {
        $zona = ZonaImpresion::factory()->create(['cara' => Cara::Trasera]);

        $this->assertSame(Cara::Trasera, $zona->fresh()->cara);
    }
}
