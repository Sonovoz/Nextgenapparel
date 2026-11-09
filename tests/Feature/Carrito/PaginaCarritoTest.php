<?php

namespace Tests\Feature\Carrito;

use App\Dominio\Carrito\CarritoActual;
use App\Dominio\Carrito\Maqueta;
use App\Dominio\Catalogo\Cara;
use App\Models\Carrito;
use App\Models\Color;
use App\Models\Cupon;
use App\Models\Diseno;
use App\Models\Familia;
use App\Models\FotoZona;
use App\Models\ImpresionCarrito;
use App\Models\Prenda;
use App\Models\Talla;
use App\Models\Variante;
use App\Models\ZonaImpresion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * «Añadir a mi pedido» y la página del carrito (paso 5.6). La lógica está probada en
 * CarritoTest; aquí, lo que añaden las rutas: el envío del configurador, la cookie del
 * invitado, la página, cambiar cantidades, quitar, el cupón y quién ve las miniaturas.
 */
class PaginaCarritoTest extends TestCase
{
    use RefreshDatabase;

    private Prenda $camiseta;

    private Color $negro;

    private Talla $m;

    private ZonaImpresion $frontal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->negro = Color::factory()->create(['nombre' => ['es' => 'Negro'], 'codigo_hex' => '#1C1C1F']);
        $this->m = Talla::factory()->create(['nombre' => 'M']);
        // Frontal de D99: 28 × 38 cm, su borde de arriba a 7,5 cm del cuello y centrada.
        $this->frontal = ZonaImpresion::factory()->create(['clave' => 'frontal', 'nombre' => ['es' => 'Frontal'], 'ancho_max_mm' => 280, 'alto_max_mm' => 380, 'desde_cuello_mm' => 75, 'desde_centro_mm' => 0]);
        $this->camiseta = Prenda::factory()->for(Familia::factory()->create())->create(['nombre' => ['es' => 'Camiseta']]);
        Variante::factory()->for($this->camiseta)->create(['color_id' => $this->negro->id, 'talla_id' => $this->m->id, 'precio_base' => 400]);
        $this->camiseta->zonas()->attach($this->frontal->id, ['precio' => 300, 'coste' => 100]);
    }

    public function test_el_configurador_anade_una_linea_por_color_y_el_invitado_recibe_su_cookie(): void
    {
        $blanco = Color::factory()->create(['nombre' => ['es' => 'Blanco']]);
        Variante::factory()->for($this->camiseta)->create(['color_id' => $blanco->id, 'talla_id' => $this->m->id, 'precio_base' => 400]);

        $respuesta = $this->postJson('/es/carrito/lineas', $this->envio([
            'lineas' => [['color' => $this->negro->id, 'tallas' => [$this->m->id => 10]], ['color' => $blanco->id, 'tallas' => [$this->m->id => 5]]],
            'impresiones' => [$this->frontal->id => $this->texto()],
        ]));

        $respuesta->assertCreated()->assertJson(['url' => url('/es/carrito'), 'lineas' => 2])->assertCookie(CarritoActual::COOKIE);
        $carrito = Carrito::sole();
        $this->assertNull($carrito->cliente_id);
        $this->assertSame(['NEXTGEN', 2, 'dorado'], [$carrito->lineas[0]->impresiones->sole()->texto, $carrito->lineas[0]->impresiones->sole()->fuente, $carrito->lineas[0]->impresiones->sole()->tinta]);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function enviosNoValidos(): array
    {
        return [
            'fuera de la zona (por encima del cuello de la zona)' => [['desde_cuello_mm' => 40], 'El tamaño o la posición del diseño no cabe en la zona. Vuelve a colocarlo.'],
            'fuera de la zona (por un lado)' => [['desde_centro_mm' => 100], 'El tamaño o la posición del diseño no cabe en la zona. Vuelve a colocarlo.'],
            'texto vacío' => [['texto' => '   '], 'Revisa el texto: entre 1 y 60 caracteres, con una de las fuentes y tintas del configurador.'],
            'tinta que no existe' => [['tinta' => 'fucsia'], 'Revisa el texto: entre 1 y 60 caracteres, con una de las fuentes y tintas del configurador.'],
            'texto demasiado largo' => [['texto' => str_repeat('A', 61)], 'Revisa el texto: entre 1 y 60 caracteres, con una de las fuentes y tintas del configurador.'],
        ];
    }

    #[DataProvider('enviosNoValidos')]
    public function test_lo_que_no_vale_no_se_anade_y_se_explica(array $cambios, string $mensaje): void
    {
        $this->postJson('/es/carrito/lineas', $this->envio(['impresiones' => [$this->frontal->id => [...$this->texto(), ...$cambios]]]))
            ->assertStatus(422)->assertJson(['message' => $mensaje]);

        $this->assertSame(0, ImpresionCarrito::count());
    }

    public function test_si_un_color_no_vale_no_se_anade_ninguno(): void
    {
        $this->postJson('/es/carrito/lineas', $this->envio(['lineas' => [
            ['color' => $this->negro->id, 'tallas' => [$this->m->id => 10]], ['color' => 999, 'tallas' => [$this->m->id => 5]],
        ]]))->assertStatus(422);

        $this->assertSame(0, ImpresionCarrito::count());
    }

    public function test_la_pagina_muestra_las_lineas_el_total_del_motor_y_deja_cambiar_quitar_y_poner_cupon(): void
    {
        Cupon::create(['codigo' => 'VERANO10', 'tipo' => 'porcentaje', 'valor' => 1000]);
        $this->get('/es/carrito')->assertOk()->assertSee('Todavía no has añadido ninguna prenda');

        // El cliente de pruebas no guarda cookies entre peticiones: se pasa la del carrito.
        $token = $this->postJson('/es/carrito/lineas', $this->envio())->assertCreated()->getCookie(CarritoActual::COOKIE)->getValue();
        $this->withCookie(CarritoActual::COOKIE, $token);
        $linea = Carrito::sole()->lineas()->sole();

        $this->get('/es/carrito')->assertOk()
            ->assertSee('Camiseta')->assertSee('«NEXTGEN»')->assertSee('20 × 6 cm')
            ->assertSee('101,64 €') // 12 × 7,00 € + 21 % IVA
            ->assertSee('noindex', false)->assertSee('<span class="contador-carrito">1', false);

        $this->patch('/es/carrito/lineas/'.$linea->uuid, ['tallas' => [$this->m->id => 15]])->assertRedirect('/es/carrito#linea-'.$linea->uuid);
        $this->assertSame([$this->m->id => 15], $linea->fresh()->tallas);
        $this->from('/es/carrito')->patch('/es/carrito/lineas/'.$linea->uuid, ['tallas' => [$this->m->id => 0]])->assertSessionHasErrors('linea_'.$linea->uuid);

        $this->post('/es/carrito/cupon', ['cupon' => 'verano10'])->assertRedirect('/es/carrito#cupon');
        $this->get('/es/carrito')->assertSee('VERANO10')->assertSee('−10,50 €');

        $this->delete('/es/carrito/lineas/'.$linea->uuid)->assertRedirect('/es/carrito');
        $this->assertModelMissing($linea);
    }

    public function test_sin_carrito_no_se_puede_cambiar_nada_y_en_ingles_la_ruta_es_cart(): void
    {
        $this->delete('/es/carrito/lineas/00000000-0000-0000-0000-000000000001')->assertNotFound();
        $this->post('/es/carrito/cupon', ['cupon' => 'X'])->assertNotFound();
        $this->get('/en/cart')->assertOk()->assertSee('Your order');
    }

    public function test_la_miniatura_solo_se_ve_si_el_diseno_esta_en_tu_carrito(): void
    {
        Storage::fake('disenos');
        Storage::disk('disenos')->put('mini.webp', 'webp');
        $diseno = Diseno::forceCreate(['uuid' => '00000000-0000-0000-0000-0000000000aa', 'propietario' => 'otra-visita', 'nombre_original' => 'logo.png',
            'ruta' => 'x.png', 'miniatura' => 'mini.webp', 'bytes' => 1, 'sha256' => str_repeat('a', 64), 'ancho_px' => 3000, 'alto_px' => 3000,
            'util_x' => 0, 'util_y' => 0, 'util_ancho_px' => 3000, 'util_alto_px' => 3000, 'fondo' => 'transparente', 'profundidad_bits' => 8,
            'derechos_declarados_en' => now(), 'derechos_texto' => 'Declaro…']);
        $url = '/es/carrito/mis-disenos/'.$diseno->uuid;

        $this->get($url)->assertNotFound();

        $carrito = Carrito::create(['token' => hash('sha256', $token = str_repeat('t', 40))]);
        $linea = $carrito->lineas()->create(['tipo' => 'prenda', 'prenda_id' => $this->camiseta->id, 'color_id' => $this->negro->id, 'tallas' => [$this->m->id => 12]]);
        $linea->impresiones()->create(['zona_impresion_id' => $this->frontal->id, 'diseno_id' => $diseno->id, 'ancho_mm' => 200, 'alto_mm' => 200, 'desde_cuello_mm' => 75, 'desde_centro_mm' => 0]);

        $this->withCookie(CarritoActual::COOKIE, $token)->get($url)->assertOk()->assertHeader('Content-Type', 'image/webp');
    }

    public function test_la_maqueta_coloca_el_diseno_con_los_mm_guardados(): void
    {
        $pecho = ZonaImpresion::factory()->create(['ancho_max_mm' => 90, 'alto_max_mm' => 90, 'desde_cuello_mm' => 75, 'desde_centro_mm' => -100]);
        $rect = new FotoZona(['x' => 5480, 'y' => 2260, 'ancho' => 790, 'alto' => 660]);
        $impresion = new ImpresionCarrito(['ancho_mm' => 45, 'alto_mm' => 45, 'desde_cuello_mm' => 75, 'desde_centro_mm' => -100]);
        $impresion->setRelation('zona', $pecho);

        // Centrado en horizontal, arriba del todo, a la mitad de la zona.
        $this->assertSame(['left' => 56.78, 'top' => 22.6, 'width' => 3.95, 'height' => 3.3], Maqueta::posicion($impresion, $rect, Cara::Delantera));

        // 2 cm más hacia su izquierda: de frente se ve a la derecha de la foto; de espaldas, a la izquierda.
        $impresion->desde_centro_mm = -120;
        $this->assertGreaterThan(56.78, Maqueta::posicion($impresion, $rect, Cara::Delantera)['left']);
        $this->assertLessThan(56.78, Maqueta::posicion($impresion, $rect, Cara::Trasera)['left']);
    }

    private function texto(): array
    {
        return ['texto' => 'NEXTGEN', 'fuente' => 2, 'tinta' => 'dorado', 'ancho_mm' => 200, 'alto_mm' => 60, 'desde_cuello_mm' => 75, 'desde_centro_mm' => 0];
    }

    private function envio(array $cambios = []): array
    {
        return [...[
            'prenda' => $this->camiseta->id,
            'lineas' => [['color' => $this->negro->id, 'tallas' => [$this->m->id => 12]]],
            'impresiones' => [$this->frontal->id => $this->texto()],
        ], ...$cambios];
    }
}
