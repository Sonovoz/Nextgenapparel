<?php

namespace Tests\Feature\Precios;

use App\Dominio\Catalogo\Cara;
use App\Dominio\Catalogo\Escaparate;
use App\Dominio\Precios\Configurador;
use App\Models\Color;
use App\Models\Familia;
use App\Models\Prenda;
use App\Models\ReglaMinimo;
use App\Models\ReglaPrecio;
use App\Models\Talla;
use App\Models\Variante;
use App\Models\ZonaImpresion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Precio en directo del configurador (D142). Valores de la ficha, sin IVA:
 * camiseta 4,00 € (XXL 5,00 €), frontal 3,00 €, espalda grande 4,00 €.
 * Mínimo 15 prendas; tramo ≥ 50 prendas −10 % en prendas. IVA 21 %.
 */
class ConfiguradorTest extends TestCase
{
    use RefreshDatabase;

    private Familia $familia;

    private Prenda $camiseta;

    /** @var array<string, int> */
    private array $zonas = [];

    /** @var array<string, int> */
    private array $tallas = [];

    /** @var array<string, int> */
    private array $colores = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->familia = Familia::factory()->create(['nombre' => ['es' => 'Camisetas', 'en' => 'T-shirts'], 'slug' => ['es' => 'camisetas', 'en' => 't-shirts']]);
        $this->camiseta = Prenda::factory()->for($this->familia)->create(['slug' => ['es' => 'camiseta', 'en' => 't-shirt']]);
        foreach (['Blanco' => 1, 'Negro' => 2] as $nombre => $orden) {
            $this->colores[$nombre] = Color::factory()->create(['nombre' => ['es' => $nombre], 'orden' => $orden])->id;
        }
        // Creadas en desorden para comprobar que se ordenan por talla.
        foreach (['XXL' => 4, 'S' => 1, 'M' => 2, 'L' => 3] as $nombre => $orden) {
            $this->tallas[$nombre] = Talla::factory()->create(['nombre' => $nombre, 'orden' => $orden])->id;
            foreach ($this->colores as $color) {
                Variante::factory()->for($this->camiseta)->create(['color_id' => $color, 'talla_id' => $this->tallas[$nombre], 'precio_base' => $nombre === 'XXL' ? 500 : 400]);
            }
        }
        foreach (['frontal' => [Cara::Delantera, 300], 'espalda' => [Cara::Trasera, 400], 'manga' => [Cara::Delantera, 150]] as $clave => [$cara, $precio]) {
            $this->zonas[$clave] = ZonaImpresion::factory()->create(['clave' => $clave, 'cara' => $cara])->id;
            if ($clave !== 'manga') { // la manga existe, pero esta prenda no la ofrece
                $this->camiseta->zonas()->attach($this->zonas[$clave], ['precio' => $precio, 'coste' => 100]);
            }
        }
        ReglaMinimo::create(['nombre' => ['es' => 'Mínimo general'], 'tipo' => 'unidades_total', 'valor' => 15, 'ambito' => 'catalogo']);
        ReglaPrecio::create(['nombre' => ['es' => 'Tramo 50'], 'tipo' => 'tramo_cantidad', 'condiciones' => ['minimo_unidades' => 50],
            'efecto' => 'porcentaje', 'valor' => 1000, 'aplica_a' => 'prendas', 'ambito' => 'catalogo']);
    }

    private function calcular(array $zonas, array $cantidades = [], string $color = 'Negro'): array
    {
        $porColor = [];
        foreach ($cantidades as $nombreColor => $tallas) {
            foreach ($tallas as $talla => $unidades) {
                $porColor[$this->colores[$nombreColor]][$this->tallas[$talla]] = $unidades;
            }
        }

        return app(Configurador::class)->calcular($this->camiseta, $this->colores[$color], array_map(fn ($z) => $this->zonas[$z], $zonas), $porColor);
    }

    /** Sin cantidades: una prenda con sus diseños (talla más barata + zonas), con IVA. */
    #[DataProvider('unaPrenda')]
    public function test_precio_de_una_prenda_segun_los_disenos(array $zonas, ?int $porUnidad, int $disenos): void
    {
        $r = $this->calcular($zonas);

        $this->assertSame($porUnidad, $r['por_unidad']);
        $this->assertSame($disenos, $r['disenos']);
        $this->assertSame([null, 0, false], [$r['total'], $r['unidades'], $r['confirmable']]);
    }

    public static function unaPrenda(): array
    {
        return [
            'sin diseño: sin precio propio' => [[], null, 0],
            'frontal: (4 + 3) × 1,21' => [['frontal'], 847, 1],
            'frontal y espalda: (4 + 3 + 4) × 1,21' => [['frontal', 'espalda'], 1331, 2],
            'la misma zona dos veces cuenta una' => [['frontal', 'frontal'], 847, 1],
            'zona que la prenda no ofrece' => [['frontal', 'manga'], null, 2],
        ];
    }

    public function test_avisa_si_el_precio_cambia_segun_la_talla(): void
    {
        $this->assertTrue($this->calcular(['frontal'])['varia']);

        Variante::where('precio_base', 500)->update(['precio_base' => 400]);
        $this->assertFalse($this->calcular(['frontal'])['varia']);
    }

    /** Con cantidades: el pedido de esa prenda según el motor (sin envío). */
    #[DataProvider('pedidos')]
    public function test_pedido_con_tallas_y_colores(array $zonas, array $cantidades, int $unidades, int $total, int $porUnidad, bool $confirmable): void
    {
        $r = $this->calcular($zonas, $cantidades);

        $this->assertSame([$unidades, $total, $porUnidad, $confirmable], [$r['unidades'], $r['total'], $r['por_unidad'], $r['confirmable']]);
    }

    public static function pedidos(): array
    {
        return [
            // 15 × 7,00 = 105,00 + 21 % = 127,05
            '15 negras M con frontal' => [['frontal'], ['Negro' => ['M' => 15]], 15, 12705, 847, true],
            // Dos colores en el mismo pedido: 5 blancas + 10 negras, mismas zonas
            'dos colores suman para el mínimo' => [['frontal'], ['Blanco' => ['S' => 5], 'Negro' => ['M' => 5, 'L' => 5]], 15, 12705, 847, true],
            // 14 × 4 + 1 XXL × 5 = 61 + 15 × 7 (zonas) = 166 → 200,86; media 13,39
            'la XXL cuesta más' => [['frontal', 'espalda'], ['Negro' => ['M' => 14, 'XXL' => 1]], 15, 20086, 1339, true],
            // (200 × 0,9 + 150) × 1,21 = 399,30; media 7,986 → 7,99
            'tramo de 50: −10 % en prendas' => [['frontal'], ['Negro' => ['M' => 50]], 50, 39930, 799, true],
            // Las tallas a cero no cuentan
            'por debajo del mínimo' => [['frontal'], ['Negro' => ['M' => 14, 'L' => 0], 'Blanco' => ['S' => 0]], 14, 11858, 847, false],
        ];
    }

    public function test_los_avisos_explican_el_minimo_y_la_oferta(): void
    {
        $this->assertSame(['El mínimo para el pedido es de 15 prendas. Llevas 14: te falta 1.'], $this->calcular(['frontal'], ['Negro' => ['M' => 14]])['impedimentos']);
        $this->assertStringContainsString('«Tramo 50»', $this->calcular(['frontal'], ['Negro' => ['M' => 50]])['avisos'][0]);
    }

    public function test_con_cantidades_y_sin_diseno_pide_elegir_una_zona(): void
    {
        $r = $this->calcular([], ['Negro' => ['M' => 20]]);

        $this->assertFalse($r['confirmable']);
        $this->assertNull($r['total']);
        $this->assertStringStartsWith('Elige al menos una zona de impresión', $r['impedimentos'][0]);
    }

    public function test_cada_color_lleva_sus_tallas_a_la_venta_en_orden(): void
    {
        Variante::where('color_id', $this->colores['Blanco'])->where('talla_id', $this->tallas['S'])->update(['activa' => false]);

        $colores = collect(Escaparate::colores($this->camiseta))->keyBy('id');

        $this->assertSame(['S', 'M', 'L', 'XXL'], array_column($colores[$this->colores['Negro']]['tallas'], 'nombre'));
        $this->assertSame(['M', 'L', 'XXL'], array_column($colores[$this->colores['Blanco']]['tallas'], 'nombre'));
    }

    // ── La ruta pública ──────────────────────────────────────────────

    public function test_la_ruta_devuelve_importes_formateados_en_cada_idioma(): void
    {
        $consulta = ['color' => $this->colores['Negro'], 'zonas' => [$this->zonas['frontal']], 'cantidades' => [$this->colores['Negro'] => [$this->tallas['M'] => 15]]];

        $this->getJson('/es/camisetas/camiseta/precio?'.http_build_query($consulta))
            ->assertOk()->assertJson(['por_unidad' => '8,47 €', 'total' => '127,05 €', 'unidades' => 15, 'confirmable' => true]);
        $this->getJson('/en/t-shirts/t-shirt/precio?'.http_build_query(['color' => $this->colores['Negro'], 'zonas' => [$this->zonas['frontal']]]))
            ->assertOk()->assertJson(['por_unidad' => '€8.47', 'total' => null, 'disenos' => 1]);
        $this->getJson('/en/t-shirts/t-shirt/precio?'.http_build_query(['color' => $this->colores['Negro'], 'zonas' => [$this->zonas['frontal']], 'cantidades' => [$this->colores['Negro'] => [$this->tallas['M'] => 3]]]))
            ->assertJsonPath('impedimentos.0', 'The minimum for the order is 15 garments. You have 3: 12 more to go.');
    }

    #[DataProvider('entradasNoValidas')]
    public function test_la_ruta_rechaza_entradas_no_validas(array $consulta): void
    {
        $this->getJson('/es/camisetas/camiseta/precio?'.http_build_query($consulta))->assertStatus(422);
    }

    public static function entradasNoValidas(): array
    {
        return [
            'sin color' => [['zonas' => [1]]],
            'cantidad negativa' => [['color' => 1, 'cantidades' => [1 => [1 => -2]]]],
            'cantidad con decimales' => [['color' => 1, 'cantidades' => [1 => [1 => '1.5']]]],
            'más de 10.000 por talla' => [['color' => 1, 'cantidades' => [1 => [1 => 10001]]]],
            'talla que no es un número' => [['color' => 1, 'cantidades' => [1 => ['xl' => 2]]]],
        ];
    }

    public function test_una_prenda_inactiva_no_tiene_precio(): void
    {
        $this->camiseta->update(['activa' => false]);

        $this->getJson('/es/camisetas/camiseta/precio?color=1')->assertNotFound();
    }
}
