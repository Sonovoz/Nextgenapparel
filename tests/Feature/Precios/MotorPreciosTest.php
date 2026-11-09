<?php

namespace Tests\Feature\Precios;

use App\Dominio\Catalogo\Cara;
use App\Dominio\Precios\Motor\Cesta;
use App\Dominio\Precios\Motor\Destino;
use App\Dominio\Precios\Motor\LineaCesta;
use App\Dominio\Precios\Motor\MotorPrecios;
use App\Dominio\Precios\Motor\PackCesta;
use App\Dominio\Precios\Motor\Resultado;
use App\Models\Caja;
use App\Models\ClaseImpuesto;
use App\Models\Color;
use App\Models\Cupon;
use App\Models\Familia;
use App\Models\GrupoCliente;
use App\Models\Pack;
use App\Models\Prenda;
use App\Models\ReglaMinimo;
use App\Models\ReglaPrecio;
use App\Models\Talla;
use App\Models\Variante;
use App\Models\ZonaEnvio;
use App\Models\ZonaImpresion;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Casos de la ficha (§ 6 y § 6 bis) con sus valores de ejemplo, sin IVA:
 * camiseta 4,00 €; polo 7,00 €; sudadera 12,00 €; frontal 3,00 €;
 * espalda grande 4,00 €; pecho 2,00 €. Mínimo 15 prendas. Tramo ≥ 50
 * prendas −10 % en prendas. Combinada ≥ 15 camisetas + ≥ 15 polos o
 * sudaderas −12 % en todo. Envíos y cupones: casos E y C de la ficha.
 */
class MotorPreciosTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, Prenda> */
    private array $prendas = [];

    /** @var array<string, int> */
    private array $zonas = [];

    /** @var array<string, int> */
    private array $tallas = [];

    private Color $negro;

    protected function setUp(): void
    {
        parent::setUp();

        // La clase general (21 % + 5,2 %) la crea la migración.
        $this->assertSame([2100, 520], [ClaseImpuesto::porDefecto()->tipo_iva, ClaseImpuesto::porDefecto()->recargo]);
        $this->negro = Color::factory()->create(['nombre' => ['es' => 'Negro']]);
        foreach (['S', 'M', 'L', 'XL', 'XXL'] as $orden => $nombre) {
            $this->tallas[$nombre] = Talla::factory()->create(['nombre' => $nombre, 'orden' => $orden])->id;
        }
        foreach (['frontal' => Cara::Delantera, 'pecho' => Cara::Delantera, 'espalda' => Cara::Trasera] as $clave => $cara) {
            $this->zonas[$clave] = ZonaImpresion::factory()->create(['clave' => $clave, 'cara' => $cara])->id;
        }

        $datos = [
            'camiseta' => ['Camisetas', 1, 400, 150, ['frontal' => 300, 'pecho' => 200, 'espalda' => 400]],
            'polo' => ['Polos', 2, 700, 210, ['pecho' => 200, 'espalda' => 400]],
            'sudadera' => ['Sudaderas', 3, 1200, 480, ['frontal' => 300, 'pecho' => 200, 'espalda' => 400]],
        ];
        foreach ($datos as $clave => [$familia, $orden, $precio, $peso, $zonas]) {
            $f = Familia::factory()->create(['nombre' => ['es' => $familia], 'orden' => $orden]);
            $prenda = Prenda::factory()->for($f)->create(['nombre' => ['es' => ucfirst($clave)]]);
            foreach ($this->tallas as $talla) {
                Variante::factory()->for($prenda)->create(['color_id' => $this->negro->id, 'talla_id' => $talla, 'precio_base' => $precio, 'peso_gramos' => $peso]);
            }
            foreach ($zonas as $zona => $precioZona) {
                $prenda->zonas()->attach($this->zonas[$zona], ['precio' => $precioZona, 'coste' => 100]);
            }
            $this->prendas[$clave] = $prenda;
        }

        ReglaMinimo::create(['nombre' => ['es' => 'Mínimo general'], 'tipo' => 'unidades_total', 'valor' => 15, 'ambito' => 'catalogo']);
        ReglaPrecio::create(['nombre' => ['es' => 'Tramo 50'], 'tipo' => 'tramo_cantidad', 'condiciones' => ['minimo_unidades' => 50],
            'efecto' => 'porcentaje', 'valor' => 1000, 'aplica_a' => 'prendas', 'ambito' => 'catalogo']);
        ReglaPrecio::create(['nombre' => ['es' => 'Combinado 15+15'], 'tipo' => 'combinada', 'efecto' => 'porcentaje', 'valor' => 1200, 'aplica_a' => 'todo', 'ambito' => 'catalogo',
            'condiciones' => ['grupos' => [
                ['ambito' => 'familia', 'ids' => [$this->prendas['camiseta']->familia_id], 'minimo' => 15],
                ['ambito' => 'familia', 'ids' => [$this->prendas['polo']->familia_id, $this->prendas['sudadera']->familia_id], 'minimo' => 15],
            ]]]);
    }

    private function linea(string $prenda, int $unidades, array $zonas, string $talla = 'M'): LineaCesta
    {
        return new LineaCesta($this->prendas[$prenda]->id, $this->negro->id, [$this->tallas[$talla] => $unidades], array_map(fn ($z) => $this->zonas[$z], $zonas));
    }

    private function calcular(array $lineas = [], array $packs = [], ...$opciones): Resultado
    {
        return app(MotorPrecios::class)->calcular(new Cesta($lineas, $packs, ...$opciones));
    }

    // ── P1–P7 (ficha § 6) ─────────────────────────────────────────────

    public function test_p1_quince_camisetas_con_frontal(): void
    {
        $r = $this->calcular([$this->linea('camiseta', 15, ['frontal'])]);

        $this->assertSame(10500, $r->subtotal());
        $this->assertSame(2205, $r->totalImpuestos());
        $this->assertSame(12705, $r->total());
        $this->assertTrue($r->confirmable());
    }

    /**
     * Tarifa de grupo (v2, D192): descuento % sobre prendas e impresión que compite con las ofertas
     * (se aplica la que más ahorra). 50 camisetas: prendas 200,00 + impresión 150,00; «Tramo 50» = 10 % de las prendas (20,00).
     */
    #[DataProvider('grupos')]
    public function test_la_tarifa_del_grupo_compite_con_las_ofertas(int $descuento, bool $activo, int $unidades, int $esperado, ?string $gana): void
    {
        $grupo = GrupoCliente::create(['nombre' => ['es' => 'Revendedor'], 'descuento' => $descuento, 'activo' => $activo]);

        $r = $this->calcular([$this->linea('camiseta', $unidades, ['frontal'])], grupoId: $grupo->id);

        $this->assertSame($esperado, $r->descuentos());
        $this->assertSame($gana, $r->oferta === null ? null : ($r->oferta['grupo'] ?? null ? 'grupo' : 'oferta'));
    }

    public static function grupos(): array
    {
        return [
            'P1 con un 15 %: 105,00 → 15,75' => [1500, true, 15, 1575, 'grupo'],
            '50 uds., grupo 5 % (17,50) < tramo (20,00): gana la oferta' => [500, true, 50, 2000, 'oferta'],
            '50 uds., grupo 10 % (35,00) > tramo (20,00): gana el grupo' => [1000, true, 50, 3500, 'grupo'],
            'grupo desactivado: nada' => [1500, false, 15, 0, null],
        ];
    }

    public function test_p2_catorce_camisetas_no_llegan_al_minimo(): void
    {
        $r = $this->calcular([$this->linea('camiseta', 14, ['frontal'])]);

        $this->assertFalse($r->confirmable());
        $this->assertSame(['El mínimo para el pedido es de 15 prendas. Llevas 14: te falta 1.'], $r->textos('impedimentos', 'es'));
    }

    public function test_p3_combinada_quince_camisetas_y_quince_polos(): void
    {
        $r = $this->calcular([$this->linea('camiseta', 15, ['frontal']), $this->linea('polo', 15, ['pecho'])]);

        $this->assertSame(21120, $r->subtotal()); // (105,00 + 135,00) × 0,88
        $this->assertSame('Combinado 15+15', $r->oferta['nombre']);
        $this->assertStringStartsWith('Cumples dos condiciones para un mejor precio: aplicamos «Combinado 15+15»', $r->textos('avisos', 'es')[0]);
    }

    public function test_p4_cumple_el_minimo_y_avisa_de_la_oferta_cercana(): void
    {
        $r = $this->calcular([$this->linea('camiseta', 10, ['frontal']), $this->linea('sudadera', 5, ['frontal'])]);

        $this->assertSame(14500, $r->subtotal());
        $this->assertNull($r->oferta);
        $this->assertTrue($r->confirmable());
        $this->assertContains('Te faltan 5 camisetas y 10 sudaderas para conseguir «Combinado 15+15».', $r->textos('avisos', 'es'));
    }

    public function test_p5_tramo_de_cincuenta_en_prendas(): void
    {
        $r = $this->calcular([$this->linea('camiseta', 50, ['frontal', 'espalda'])]);

        $this->assertSame(53000, $r->subtotal()); // 50 × 4,00 × 0,90 + 50 × 7,00
        $this->assertSame('Tramo 50', $r->oferta['nombre']);
    }

    public function test_p6_se_aplica_la_mejor_oferta_para_el_cliente(): void
    {
        $r = $this->calcular([$this->linea('camiseta', 50, ['frontal']), $this->linea('sudadera', 15, ['frontal'])]);

        // Tramo: 537,00 €; combinada: 506,00 € → la combinada.
        $this->assertSame(50600, $r->subtotal());
        $this->assertSame('Combinado 15+15', $r->oferta['nombre']);
        $this->assertSame(6900, $r->oferta['importe']);
    }

    public function test_p7_autonomo_con_recargo_de_equivalencia(): void
    {
        $r = $this->calcular([$this->linea('camiseta', 15, ['frontal'])], recargoEquivalencia: true);

        $this->assertSame([['tipo_iva' => 2100, 'recargo' => 520, 'base' => 10500, 'cuota' => 2205, 'cuota_recargo' => 546]], $r->impuestos);
        $this->assertSame(13251, $r->total());
    }

    // ── K1–K4 (ficha § 6 bis) ─────────────────────────────────────────

    private function packJoven(string $reparto = 'fijo'): Pack
    {
        $pack = Pack::create(['nombre' => ['es' => 'Pack joven'], 'slug' => ['es' => 'pack-joven'], 'precio' => 8182, 'reparto' => $reparto]);
        $pack->lineas()->create([
            'prenda_id' => $this->prendas['camiseta']->id, 'unidades' => 10, 'colores' => [$this->negro->id],
            'reparto_tallas' => $reparto === 'fijo' ? [$this->tallas['L'] => 2, $this->tallas['XL'] => 5, $this->tallas['XXL'] => 3] : null,
            'tallas_permitidas' => $reparto === 'libre' ? [$this->tallas['L'], $this->tallas['XL'], $this->tallas['XXL']] : null,
        ]);
        $pack->zonas()->attach($this->zonas['frontal']);

        return $pack;
    }

    public function test_k1_pack_con_reparto_fijo_cumple_el_minimo_y_cuesta_99_euros(): void
    {
        $r = $this->calcular(packs: [new PackCesta($this->packJoven()->id)]);

        $this->assertSame(8182, $r->subtotal());
        $this->assertSame(9900, $r->total()); // 99,00 € con IVA
        $this->assertTrue($r->confirmable()); // 10 prendas < 15, pero el pack cumple solo
        $this->assertSame([$this->tallas['L'] => 2, $this->tallas['XL'] => 5, $this->tallas['XXL'] => 3], $r->lineas[0]->tallas);
    }

    public function test_k2_una_zona_extra_se_cobra_con_su_tarifa(): void
    {
        $r = $this->calcular(packs: [new PackCesta($this->packJoven()->id, zonasExtra: [$this->zonas['espalda']])]);

        $this->assertSame(12182, $r->subtotal()); // 81,82 + 10 × 4,00
    }

    public function test_k3_el_cupon_no_se_aplica_al_pack(): void
    {
        Cupon::create(['codigo' => 'VERANO10', 'tipo' => 'porcentaje', 'valor' => 1000]);

        $r = $this->calcular(packs: [new PackCesta($this->packJoven()->id)], cupon: 'VERANO10');

        $this->assertSame(8182, $r->subtotal());
        $this->assertNull($r->cupon);
        $this->assertContains('Los packs ya tienen su precio especial: el cupón VERANO10 no se aplica.', $r->textos('avisos', 'es'));
    }

    public function test_k4_con_reparto_libre_hay_que_repartir_todas_las_unidades(): void
    {
        $pack = $this->packJoven('libre');
        $linea = $pack->lineas()->sole();
        $tallas = [$this->tallas['L'] => 4, $this->tallas['XL'] => 5];

        $r = $this->calcular(packs: [new PackCesta($pack->id, [$linea->id => ['color' => $this->negro->id, 'tallas' => $tallas]])]);
        $this->assertFalse($r->confirmable());
        $this->assertSame(['Te falta repartir 1 prenda de Pack joven.'], $r->textos('impedimentos', 'es'));

        $tallas[$this->tallas['XXL']] = 1;
        $this->assertTrue($this->calcular(packs: [new PackCesta($pack->id, [$linea->id => ['color' => $this->negro->id, 'tallas' => $tallas]])])->confirmable());
    }

    public function test_un_pack_no_admite_tallas_fuera_de_las_permitidas(): void
    {
        $pack = $this->packJoven('libre');
        $linea = $pack->lineas()->sole();

        $r = $this->calcular(packs: [new PackCesta($pack->id, [$linea->id => ['color' => $this->negro->id, 'tallas' => [$this->tallas['S'] => 10]]])]);

        $this->assertSame(['Revisa el color y las tallas de Pack joven.'], $r->textos('impedimentos', 'es'));
    }

    // ── Envíos (ficha § 6, casos E) ───────────────────────────────────

    private function zonasDeEnvio(): void
    {
        $peninsula = ZonaEnvio::create(['nombre' => ['es' => 'Península'], 'paises' => ['ES'], 'metodo' => 'por_kilo', 'precio_kg_extra' => 80, 'gratis_desde' => 50000, 'plazo_dias' => 2]);
        foreach ([2000 => 490, 5000 => 690, 10000 => 990] as $hasta => $precio) {
            $peninsula->tarifas()->create(['hasta_gramos' => $hasta, 'precio' => $precio]);
        }
        ZonaEnvio::create(['nombre' => ['es' => 'Baleares'], 'paises' => ['ES'], 'prefijos_cp' => ['07'], 'metodo' => 'por_caja', 'precio_caja' => 1200, 'plazo_dias' => 4]);
        Caja::create(['nombre' => 'Caja grande', 'capacidad_prendas' => 50, 'peso_max_gramos' => 20000]);
    }

    public function test_e1_por_peso_a_la_peninsula_con_iva_sobre_el_envio(): void
    {
        $this->zonasDeEnvio();

        $r = $this->calcular([$this->linea('camiseta', 15, ['frontal'])], destino: new Destino('ES', '28001'));

        $this->assertSame(['Península', 690, 2250], [$r->envio['zona'], $r->envio['importe'], $r->envio['peso_gramos']]);
        $this->assertSame(11190 + 2350, $r->total()); // (105,00 + 6,90) + 21 %
        $this->assertContains('Te faltan 395,00 € (sin IVA) para el envío gratis.', $r->textos('avisos', 'es'));
    }

    public function test_e2_envio_gratis_desde_el_importe_de_la_zona(): void
    {
        $this->zonasDeEnvio();

        $r = $this->calcular([$this->linea('camiseta', 50, ['frontal']), $this->linea('sudadera', 15, ['frontal'])], destino: new Destino('ES', '41001'));

        $this->assertSame(0, $r->envio['importe']);
        $this->assertContains('Tu envío es gratis.', $r->textos('avisos', 'es'));
    }

    public function test_e3_por_cajas_a_baleares(): void
    {
        $this->zonasDeEnvio();

        $una = $this->calcular([$this->linea('camiseta', 50, ['frontal'])], destino: new Destino('ES', '07001'));
        $dos = $this->calcular([$this->linea('camiseta', 50, ['frontal']), $this->linea('sudadera', 15, ['frontal'])], destino: new Destino('ES', '07001'));

        $this->assertSame(['Baleares', 1, 1200], [$una->envio['zona'], $una->envio['bultos'], $una->envio['importe']]);
        $this->assertSame([2, 2400], [$dos->envio['bultos'], $dos->envio['importe']]); // 65 prendas en cajas de 50
    }

    public function test_e4_destino_sin_envio_no_se_puede_confirmar(): void
    {
        $this->zonasDeEnvio();

        $r = $this->calcular([$this->linea('camiseta', 15, ['frontal'])], destino: new Destino('FR', '75001'));

        $this->assertFalse($r->confirmable());
        $this->assertSame(['Todavía no enviamos a ese destino.'], $r->textos('impedimentos', 'es'));
    }

    public function test_e5_por_encima_del_ultimo_tramo_se_cobra_cada_kilo_extra(): void
    {
        $this->zonasDeEnvio();

        $r = $this->calcular([$this->linea('sudadera', 25, ['frontal'])], destino: new Destino('ES', '28001'));

        $this->assertSame([12000, 1150], [$r->envio['peso_gramos'], $r->envio['importe']]); // 9,90 + 2 kg × 0,80
    }

    // ── Cupones (casos C) ─────────────────────────────────────────────

    public function test_c1_cupon_de_porcentaje_tras_la_oferta_y_sin_distinguir_mayusculas(): void
    {
        Cupon::create(['codigo' => 'verano10', 'tipo' => 'porcentaje', 'valor' => 1000]);

        $r = $this->calcular([$this->linea('camiseta', 15, ['frontal'])], cupon: ' Verano10 ');

        $this->assertSame(['codigo' => 'VERANO10', 'importe' => 1050], $r->cupon);
        $this->assertSame(9450, $r->subtotal());
    }

    /** @return array<string, array{array<string, mixed>, list<string>, string}> */
    public static function cuponesQueNoSeAplican(): array
    {
        return [
            'caducado' => [['hasta' => '2026-01-01 00:00:00'], [], 'El cupón X no está vigente.'],
            'aún no empieza' => [['desde' => '2027-01-01 00:00:00'], [], 'El cupón X no está vigente.'],
            'inactivo' => [['activo' => false], [], 'El cupón X no existe o no está activo.'],
            'agotado' => [['usos_maximos' => 3, 'usos' => 3], [], 'El cupón X ya se ha agotado.'],
            'ya usado por el cliente' => [[], ['x'], 'Ya has usado el cupón X.'],
            'pedido por debajo de su mínimo' => [['minimo_importe' => 20000], [], 'El cupón X es para pedidos desde 200,00 € (sin IVA).'],
        ];
    }

    #[DataProvider('cuponesQueNoSeAplican')]
    public function test_un_cupon_no_valido_no_se_aplica_y_se_explica(array $datos, array $usados, string $mensaje): void
    {
        Cupon::create(['codigo' => 'X', 'tipo' => 'importe', 'valor' => 500, ...$datos]);

        $r = $this->calcular([$this->linea('camiseta', 15, ['frontal'])], cupon: 'X', cuponesUsados: $usados, ahora: new DateTimeImmutable('2026-11-08 12:00'));

        $this->assertNull($r->cupon);
        $this->assertSame(10500, $r->subtotal());
        $this->assertContains($mensaje, $r->textos('avisos', 'es'));
        $this->assertTrue($r->confirmable()); // un cupón no válido nunca impide comprar
    }

    public function test_un_cupon_de_importe_se_reparte_entre_las_lineas_sin_perder_centimos(): void
    {
        Cupon::create(['codigo' => 'MENOS10', 'tipo' => 'importe', 'valor' => 1001]);

        $r = $this->calcular([$this->linea('camiseta', 10, ['frontal']), $this->linea('sudadera', 5, ['frontal'])], cupon: 'MENOS10');

        $this->assertSame(1001, $r->cupon['importe']);
        $this->assertSame(1001, $r->lineas[0]->cupon + $r->lineas[1]->cupon);
        $this->assertSame(14500 - 1001, $r->subtotal());
    }

    // ── Mínimos y validación de la cesta ──────────────────────────────

    public function test_minimo_por_talla_en_una_familia(): void
    {
        ReglaMinimo::create(['nombre' => ['es' => 'Tallas'], 'tipo' => 'unidades_por_talla', 'valor' => 3, 'ambito' => 'familia', 'ambito_id' => $this->prendas['camiseta']->familia_id]);
        $linea = new LineaCesta($this->prendas['camiseta']->id, $this->negro->id, [$this->tallas['M'] => 14, $this->tallas['XL'] => 1], [$this->zonas['frontal']]);

        $r = $this->calcular([$linea]);

        $this->assertSame(['Cada talla necesita al menos 3 unidades: Camiseta en talla XL lleva 1.'], $r->textos('impedimentos', 'es'));
    }

    public function test_importe_minimo_y_reglas_fuera_de_vigencia(): void
    {
        ReglaMinimo::create(['nombre' => ['es' => 'Importe'], 'tipo' => 'importe_minimo', 'valor' => 20000, 'ambito' => 'catalogo']);
        ReglaMinimo::create(['nombre' => ['es' => 'Antigua'], 'tipo' => 'unidades_total', 'valor' => 100, 'ambito' => 'catalogo', 'hasta' => '2026-01-01']);

        $r = $this->calcular([$this->linea('camiseta', 15, ['frontal'])], ahora: new DateTimeImmutable('2026-11-08'));

        $this->assertSame(['El importe mínimo para el pedido es de 200,00 € (sin IVA). Te faltan 95,00 €.'], $r->textos('impedimentos', 'es'));
    }

    public function test_no_se_vende_sin_impresion_ni_en_una_zona_que_la_prenda_no_ofrece(): void
    {
        $sinZona = $this->calcular([$this->linea('camiseta', 15, [])]);
        $zonaAjena = $this->calcular([$this->linea('polo', 15, ['frontal'])]); // el polo no tiene frontal

        $this->assertSame(['Elige al menos una zona de impresión disponible para Camiseta.'], $sinZona->textos('impedimentos', 'es'));
        $this->assertSame(['Elige al menos una zona de impresión disponible para Polo.'], $zonaAjena->textos('impedimentos', 'es'));
    }

    public function test_una_variacion_retirada_no_se_puede_comprar(): void
    {
        $this->prendas['camiseta']->variantes()->where('talla_id', $this->tallas['M'])->update(['activa' => false]);

        $r = $this->calcular([$this->linea('camiseta', 15, ['frontal'])]);

        $this->assertSame(['Camiseta en talla M ya no está disponible en ese color.'], $r->textos('impedimentos', 'es'));
    }

    public function test_cesta_vacia_y_mensajes_en_ingles(): void
    {
        $this->assertSame(['Add at least one garment or pack.'], $this->calcular()->textos('impedimentos', 'en'));
        $this->assertSame(['The minimum for the order is 15 garments. You have 14: 1 more to go.'], $this->calcular([$this->linea('camiseta', 14, ['frontal'])])->textos('impedimentos', 'en'));
    }

    public function test_el_resultado_es_determinista_y_guarda_cada_paso(): void
    {
        $cesta = fn () => $this->calcular([$this->linea('camiseta', 50, ['frontal']), $this->linea('sudadera', 15, ['frontal'])]);

        $this->assertSame($cesta()->aArray(), $cesta()->aArray());
        $pasos = array_column($cesta()->aArray()['pasos'], 'importe', 'paso');
        $this->assertSame(['prendas' => 38000, 'impresion' => 19500, 'oferta' => -6900, 'cupon' => 0, 'envio' => 0, 'impuestos' => 10626], $pasos);
    }

    // ── Gauntlet de cierre (ronda única) ──────────────────────────────

    public function test_un_porcentaje_de_mas_del_100_no_deja_netos_negativos_ni_tumba_el_motor(): void
    {
        ReglaPrecio::query()->delete();
        ReglaPrecio::create(['nombre' => ['es' => 'Error de tecleo'], 'tipo' => 'tramo_cantidad', 'condiciones' => ['minimo_unidades' => 1],
            'efecto' => 'porcentaje', 'valor' => 15000, 'aplica_a' => 'prendas', 'ambito' => 'catalogo']);

        $r = $this->calcular([$this->linea('camiseta', 15, ['frontal'])]);

        $this->assertSame(6000, $r->oferta['importe']); // como mucho, el 100 % de las prendas
        $this->assertSame(4500, $r->subtotal()); // queda la impresión
        $this->assertGreaterThanOrEqual(0, $r->total());
    }

    public function test_un_pack_con_una_prenda_en_la_papelera_o_retirada_deja_de_venderse(): void
    {
        $pack = $this->packJoven();

        $this->prendas['camiseta']->update(['activa' => false]);
        $this->assertSame(['Ese pack ya no está disponible.'], $this->calcular(packs: [new PackCesta($pack->id)])->textos('impedimentos', 'es'));

        $this->prendas['camiseta']->update(['activa' => true]);
        $this->prendas['camiseta']->delete();
        $this->assertSame(['Ese pack ya no está disponible.'], $this->calcular(packs: [new PackCesta($pack->id)])->textos('impedimentos', 'es'));
    }

    /** @return array<string, array{mixed}> */
    public static function cantidadesNoValidas(): array
    {
        return ['decimales' => [15.5], 'negativas' => [-3], 'enormes' => [PHP_INT_MAX], 'texto' => ['15']];
    }

    #[DataProvider('cantidadesNoValidas')]
    public function test_las_cantidades_deben_ser_enteros_razonables(mixed $unidades): void
    {
        $linea = new LineaCesta($this->prendas['camiseta']->id, $this->negro->id, [$this->tallas['M'] => $unidades], [$this->zonas['frontal']]);

        $r = $this->calcular([$linea]);

        $this->assertFalse($r->confirmable());
        $this->assertSame(['Revisa las cantidades: cada talla admite de 1 a 10000 unidades.'], $r->textos('impedimentos', 'es'));
        $this->assertSame(0, $r->total());
    }

    public function test_el_porcentaje_se_redondea_una_vez_sobre_el_total(): void
    {
        Cupon::create(['codigo' => 'UNOYMEDIO', 'tipo' => 'porcentaje', 'valor' => 150]);
        $lineas = array_map(fn () => $this->linea('camiseta', 3, ['frontal']), range(1, 5)); // 5 × 21,00 €

        $r = $this->calcular($lineas, cupon: 'UNOYMEDIO');

        $this->assertSame(158, $r->cupon['importe']); // 1,5 % de 105,00 € = 1,575 → 1,58 € (no 5 × 0,32)
    }

    public function test_el_envio_tributa_como_la_mercancia_cuando_hay_tipos_mezclados(): void
    {
        $this->zonasDeEnvio();
        $this->prendas['polo']->update(['clase_impuesto_id' => ClaseImpuesto::where('clave', 'reducido')->value('id')]);

        $r = $this->calcular([$this->linea('camiseta', 15, ['frontal']), $this->linea('polo', 15, ['pecho'])], destino: new Destino('ES', '28001'));

        // Mercancía tras la combinada: 92,40 € al 21 % y 118,80 € al 10 %. Envío 9,90 € repartido: 4,33 + 5,57.
        $this->assertSame(990, $r->envio['importe']);
        $this->assertSame([
            ['tipo_iva' => 2100, 'recargo' => 0, 'base' => 9673, 'cuota' => 2031, 'cuota_recargo' => 0],
            ['tipo_iva' => 1000, 'recargo' => 0, 'base' => 12437, 'cuota' => 1244, 'cuota_recargo' => 0],
        ], $r->impuestos);
    }

    public function test_pack_y_prendas_sueltas_juntos(): void
    {
        Cupon::create(['codigo' => 'VERANO10', 'tipo' => 'porcentaje', 'valor' => 1000]);
        ReglaMinimo::create(['nombre' => ['es' => 'Tallas'], 'tipo' => 'unidades_por_talla', 'valor' => 3, 'ambito' => 'catalogo']);
        $pack = new PackCesta($this->packJoven()->id);

        // El pack cumple el mínimo de 15; el cupón va solo a la sudadera suelta.
        $r = $this->calcular([$this->linea('sudadera', 3, ['frontal'])], [$pack], cupon: 'VERANO10');
        $this->assertTrue($r->confirmable());
        $this->assertSame(['codigo' => 'VERANO10', 'importe' => 450], $r->cupon); // 10 % de 45,00 €
        $this->assertSame(8182 + 4050, $r->subtotal());
        $this->assertContains('Cupón VERANO10 aplicado a las prendas sueltas (los packs ya tienen su precio especial): ahorras 4,50 €.', $r->textos('avisos', 'es'));

        // …pero el mínimo por talla sigue valiendo para las sueltas (D127).
        $r = $this->calcular([$this->linea('sudadera', 1, ['frontal'])], [$pack]);
        $this->assertSame(['Cada talla necesita al menos 3 unidades: Sudadera en talla M lleva 1.'], $r->textos('impedimentos', 'es'));
    }

    public function test_una_oferta_de_importe_fijo_se_reparte(): void
    {
        ReglaPrecio::query()->delete();
        ReglaPrecio::create(['nombre' => ['es' => '10 € menos'], 'tipo' => 'tramo_cantidad', 'condiciones' => ['minimo_unidades' => 15],
            'efecto' => 'importe', 'valor' => 1000, 'aplica_a' => 'todo', 'ambito' => 'catalogo']);

        $r = $this->calcular([$this->linea('camiseta', 10, ['frontal']), $this->linea('sudadera', 5, ['frontal'])]);

        $this->assertSame(1000, $r->oferta['importe']);
        $this->assertSame(14500 - 1000, $r->subtotal());
    }

    public function test_la_zona_con_prefijo_gana_aunque_otra_tenga_mas_prioridad(): void
    {
        $this->zonasDeEnvio();
        ZonaEnvio::where('nombre->es', 'Península')->update(['prioridad' => 99]);

        $r = $this->calcular([$this->linea('camiseta', 15, ['frontal'])], destino: new Destino('ES', '07 001'));

        $this->assertSame('Baleares', $r->envio['zona']);
    }

    public function test_una_oferta_mal_formada_no_se_aplica_ni_rompe_el_calculo(): void
    {
        ReglaPrecio::query()->delete();
        foreach ([[['ambito' => 'familia', 'ids' => [], 'minimo' => 0]], [['ambito' => 'desconocido', 'ids' => [1], 'minimo' => 1]]] as $grupos) {
            ReglaPrecio::create(['nombre' => ['es' => 'Mal'], 'tipo' => 'combinada', 'condiciones' => ['grupos' => $grupos],
                'efecto' => 'porcentaje', 'valor' => 5000, 'aplica_a' => 'todo', 'ambito' => 'catalogo']);
        }

        $r = $this->calcular([$this->linea('camiseta', 15, ['frontal'])]);

        $this->assertNull($r->oferta);
        $this->assertSame(10500, $r->subtotal());
    }

    public function test_los_mensajes_existen_en_los_dos_idiomas(): void
    {
        $this->assertSame(array_keys(require lang_path('es/precios.php')), array_keys(require lang_path('en/precios.php')));
    }
}
