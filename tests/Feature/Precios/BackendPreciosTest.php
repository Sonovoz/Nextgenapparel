<?php

namespace Tests\Feature\Precios;

use App\Dominio\Catalogo\ZonasPack;
use App\Dominio\Precios\Motor\Cesta;
use App\Dominio\Precios\Motor\MotorPrecios;
use App\Dominio\Precios\Motor\PackCesta;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Pages\SimuladorPrecios;
use App\Filament\Resources\Precios\Pages\ManageClaseImpuesto;
use App\Filament\Resources\Precios\Pages\ManageCupon;
use App\Filament\Resources\Precios\Pages\ManagePacks;
use App\Filament\Resources\Precios\Pages\ManageReglaMinimo;
use App\Filament\Resources\Precios\Pages\ManageReglaPrecio;
use App\Filament\Resources\Precios\Pages\ManageZonaEnvio;
use App\Models\ClaseImpuesto;
use App\Models\Color;
use App\Models\Cupon;
use App\Models\Familia;
use App\Models\Pack;
use App\Models\Prenda;
use App\Models\ReglaMinimo;
use App\Models\ReglaPrecio;
use App\Models\Talla;
use App\Models\ZonaEnvio;
use App\Models\ZonaImpresion;
use Database\Seeders\EjemplosCatalogoSeeder;
use Database\Seeders\EjemplosPreciosSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BackendPreciosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(EjemplosCatalogoSeeder::class);
        Filament::setCurrentPanel('admin');
    }

    /** @return array<string, array{Rol, string, bool}> */
    public static function accesos(): array
    {
        return [
            'gestor en ofertas' => [Rol::GestorTienda, '/admin/ofertas', true],
            'gestor en cupones' => [Rol::GestorTienda, '/admin/cupones', true],
            'gestor en el simulador' => [Rol::GestorTienda, '/admin/simulador-precios', true],
            'gestor en packs' => [Rol::GestorTienda, '/admin/packs', true],
            'gestor no entra en impuestos' => [Rol::GestorTienda, '/admin/impuestos', false],
            'gestor no entra en envío' => [Rol::GestorTienda, '/admin/envio', false],
            'producción no entra en ofertas' => [Rol::Produccion, '/admin/ofertas', false],
            'producción no entra en packs' => [Rol::Produccion, '/admin/packs', false],
            'administrador en impuestos' => [Rol::Administrador, '/admin/impuestos', true],
            'administrador en cajas' => [Rol::Administrador, '/admin/cajas', true],
            'administrador en pedido mínimo' => [Rol::Administrador, '/admin/pedido-minimo', true],
        ];
    }

    #[DataProvider('accesos')]
    public function test_cada_seccion_la_ve_quien_tiene_su_permiso(Rol $rol, string $url, bool $entra): void
    {
        $respuesta = $this->actingAs($this->usuarioConRol($rol))->get($url);

        $entra ? $respuesta->assertOk() : $respuesta->assertForbidden();
    }

    public function test_solo_una_clase_de_impuesto_es_la_de_por_defecto_y_en_uso_no_se_borra(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::Administrador));
        $reducido = ClaseImpuesto::where('clave', 'reducido')->sole();

        Livewire::test(ManageClaseImpuesto::class)
            ->callAction(TestAction::make('edit')->table($reducido), ['por_defecto' => true])
            ->assertHasNoFormErrors();

        $this->assertSame(['reducido'], ClaseImpuesto::where('por_defecto', true)->pluck('clave')->all());
        $this->assertFalse(auth()->user()->can('delete', $reducido->fresh())); // es la de por defecto

        $general = ClaseImpuesto::where('clave', 'general')->sole();
        Prenda::query()->first()->update(['clase_impuesto_id' => $general->id]);
        $this->assertFalse(auth()->user()->can('delete', $general)); // la usa una prenda
        $this->assertTrue(auth()->user()->can('delete', ClaseImpuesto::where('clave', 'superreducido')->sole()));
    }

    public function test_crear_un_tramo_y_una_oferta_combinada_desde_el_backend(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));
        $familias = Familia::orderBy('orden')->get()->mapWithKeys(fn ($f) => [$f->getTranslation('slug', 'es') => $f->id]);

        Livewire::test(ManageReglaPrecio::class)
            ->callAction('create', [
                'nombre' => ['es' => 'Tramo 50'], 'tipo' => 'tramo_cantidad', 'efecto' => 'porcentaje', 'valor' => '10',
                'aplica_a' => 'prendas', 'ambito' => 'catalogo', 'condiciones' => ['minimo_unidades' => 50], 'activa' => true,
            ])
            ->assertHasNoFormErrors()
            ->callAction('create', [
                'nombre' => ['es' => 'Combinado'], 'tipo' => 'combinada', 'efecto' => 'porcentaje', 'valor' => '12,5',
                'aplica_a' => 'todo', 'ambito' => 'catalogo', 'activa' => true,
                'condiciones' => ['grupos' => [
                    ['ambito' => 'familia', 'ids' => [(string) $familias['camisetas']], 'minimo' => '15'],
                    ['ambito' => 'familia', 'ids' => [(string) $familias['polos'], (string) $familias['sudaderas']], 'minimo' => '15'],
                ]],
            ])
            ->assertHasNoFormErrors();

        $tramo = ReglaPrecio::where('tipo', 'tramo_cantidad')->sole();
        $this->assertSame([1000, ['minimo_unidades' => 50]], [$tramo->valor, $tramo->condiciones]);
        $combinada = ReglaPrecio::where('tipo', 'combinada')->sole();
        $this->assertSame(1250, $combinada->valor);
        $this->assertSame([$familias['polos'], $familias['sudaderas']], $combinada->condiciones['grupos'][1]['ids']);
        $this->assertSame(15, $combinada->condiciones['grupos'][1]['minimo']);
    }

    public function test_no_se_admiten_porcentajes_de_mas_del_100(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));

        Livewire::test(ManageCupon::class)
            ->callAction('create', ['codigo' => 'ERROR', 'tipo' => 'porcentaje', 'valor' => '150', 'activo' => true])
            ->assertHasFormErrors(['valor']);
        Livewire::test(ManageReglaPrecio::class)
            ->callAction('create', ['nombre' => ['es' => 'Error'], 'tipo' => 'tramo_cantidad', 'efecto' => 'porcentaje', 'valor' => '150',
                'aplica_a' => 'todo', 'ambito' => 'catalogo', 'condiciones' => ['minimo_unidades' => 1]])
            ->assertHasFormErrors(['valor']);
        // Un importe fijo sí puede ser 150 €.
        Livewire::test(ManageCupon::class)
            ->callAction('create', ['codigo' => 'MENOS150', 'tipo' => 'importe', 'valor' => '150', 'activo' => true])
            ->assertHasNoFormErrors();
        $this->assertSame(15000, Cupon::sole()->valor);
    }

    public function test_el_importe_minimo_se_escribe_en_euros(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));

        Livewire::test(ManageReglaMinimo::class)
            ->callAction('create', ['nombre' => ['es' => 'Importe'], 'tipo' => 'importe_minimo', 'valor' => '199,90', 'ambito' => 'catalogo', 'activa' => true])
            ->assertHasNoFormErrors()
            ->callAction('create', ['nombre' => ['es' => 'Unidades'], 'tipo' => 'unidades_total', 'valor' => '15', 'ambito' => 'catalogo', 'activa' => true])
            ->assertHasNoFormErrors();

        $this->assertSame([19990, 15], ReglaMinimo::orderBy('id')->pluck('valor')->all());
    }

    public function test_dos_tramos_de_envio_con_el_mismo_peso_dan_un_aviso(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::Administrador));

        Livewire::test(ManageZonaEnvio::class)
            ->callAction('create', [
                'nombre' => ['es' => 'Península'], 'paises' => ['ES'], 'plazo_dias' => 2, 'metodo' => 'por_kilo', 'prioridad' => 0, 'activa' => true,
                'tarifas' => [['hasta_gramos' => 2000, 'precio' => '4,90'], ['hasta_gramos' => 2000, 'precio' => '5,90']],
            ])
            ->assertHasFormErrors();
        $this->assertSame(0, ZonaEnvio::count());
    }

    public function test_el_codigo_de_cupon_se_guarda_en_mayusculas_y_no_se_repite(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));

        Livewire::test(ManageCupon::class)
            ->callAction('create', ['codigo' => 'verano10', 'tipo' => 'porcentaje', 'valor' => '10', 'un_uso_por_cliente' => true, 'activo' => true])
            ->assertHasNoFormErrors()
            ->callAction('create', ['codigo' => 'con espacio', 'tipo' => 'importe', 'valor' => '5', 'activo' => true])
            ->assertHasFormErrors(['codigo']);

        $cupon = Cupon::sole();
        $this->assertSame(['VERANO10', 1000], [$cupon->codigo, $cupon->valor]);

        $cupon->update(['usos' => 1]);
        $this->assertFalse(auth()->user()->can('delete', $cupon)); // ya usado: se desactiva
    }

    public function test_una_zona_de_envio_por_peso_guarda_sus_tarifas(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::Administrador));

        Livewire::test(ManageZonaEnvio::class)
            ->callAction('create', [
                'nombre' => ['es' => 'Península'], 'paises' => ['es'], 'prefijos_cp' => [], 'plazo_dias' => 2, 'metodo' => 'por_kilo',
                'gratis_desde' => '500', 'precio_kg_extra' => '0,80', 'prioridad' => 0, 'activa' => true,
                'tarifas' => [['hasta_gramos' => 2000, 'precio' => '4,90'], ['hasta_gramos' => 5000, 'precio' => '6,90']],
            ])
            ->assertHasNoFormErrors();

        $zona = ZonaEnvio::with('tarifas')->sole();
        $this->assertSame([['ES'], 50000, 80], [$zona->paises, $zona->gratis_desde, $zona->precio_kg_extra]);
        $this->assertSame([2000 => 490, 5000 => 690], $zona->tarifas->pluck('precio', 'hasta_gramos')->all());
    }

    private function datosPack(array $cambios = []): array
    {
        $camiseta = Prenda::where('slug->es', 'camiseta-essential')->sole();
        $negro = Color::where('nombre->es', 'Negro')->sole();
        $tallas = Talla::pluck('id', 'nombre');

        return array_replace_recursive([
            'nombre' => ['es' => 'Pack joven'], 'slug' => ['es' => 'pack-joven'], 'precio' => '81,82', 'reparto' => 'fijo',
            'zonas' => [$camiseta->zonas()->where('clave', 'frontal')->value('zonas_impresion.id')], 'activo' => true,
            'lineas' => [[
                'prenda_id' => $camiseta->id, 'unidades' => 10, 'colores' => [$negro->id],
                'reparto_tallas' => [['talla_id' => $tallas['L'], 'unidades' => 2], ['talla_id' => $tallas['XL'], 'unidades' => 5], ['talla_id' => $tallas['XXL'], 'unidades' => 3]],
            ]],
        ], $cambios);
    }

    public function test_crear_un_pack_desde_el_backend_y_venderlo_a_99_euros(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));

        Livewire::test(ManagePacks::class)->callAction('create', $this->datosPack())->assertHasNoFormErrors();

        $pack = Pack::with('lineas')->sole();
        $tallas = Talla::pluck('id', 'nombre');
        $this->assertSame(8182, $pack->precio);
        $this->assertSame([$tallas['L'] => 2, $tallas['XL'] => 5, $tallas['XXL'] => 3], $pack->lineas[0]->reparto_tallas);

        $resultado = app(MotorPrecios::class)->calcular(new Cesta(packs: [new PackCesta($pack->id)]));
        $this->assertSame(9900, $resultado->total());
        $this->assertTrue($resultado->confirmable());
    }

    public function test_editar_un_pack_conserva_y_cambia_su_reparto(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));
        Livewire::test(ManagePacks::class)->callAction('create', $this->datosPack())->assertHasNoFormErrors();
        $pack = Pack::sole();
        $tallas = Talla::pluck('id', 'nombre');

        $prueba = Livewire::test(ManagePacks::class)->mountAction(TestAction::make('edit')->table($pack));
        $lineas = $prueba->instance()->mountedActions[0]['data']['lineas'];
        $clave = array_key_first($lineas);
        $this->assertEquals([['talla_id' => $tallas['L'], 'unidades' => 2], ['talla_id' => $tallas['XL'], 'unidades' => 5], ['talla_id' => $tallas['XXL'], 'unidades' => 3]], array_values($lineas[$clave]['reparto_tallas']));

        $lineas[$clave]['reparto_tallas'] = [['talla_id' => $tallas['M'], 'unidades' => 10]];
        $prueba->fillForm(['lineas' => $lineas])->callMountedAction()->assertHasNoFormErrors();

        $this->assertSame([$tallas['M'] => 10], $pack->lineas()->sole()->reparto_tallas);
    }

    public function test_el_reparto_fijo_debe_sumar_las_unidades(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));
        $datos = $this->datosPack();
        $datos['lineas'][0]['unidades'] = 12;

        Livewire::test(ManagePacks::class)->callAction('create', $datos)->assertHasFormErrors();
        $this->assertSame(0, Pack::count());
    }

    /** D223: una zona que la prenda no ofrece no es un error: esa prenda lleva las demás. Solo falla si no lleva ninguna. */
    public function test_cada_prenda_del_pack_lleva_las_zonas_que_ofrece(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));
        $polo = Prenda::where('slug->es', 'polo-classic')->sole(); // sin frontal
        $zona = fn (string $clave) => (int) ZonaImpresion::where('clave', $clave)->value('id');
        $datos = $this->datosPack();
        $datos['lineas'][0]['prenda_id'] = $polo->id;
        $datos['lineas'][0]['colores'] = [$polo->variantes()->value('color_id')];

        // Solo frontal: el polo no llevaría nada impreso.
        Livewire::test(ManagePacks::class)->callAction('create', $datos)->assertHasFormErrors(['zonas']);

        // Frontal y espalda: el polo lleva la espalda y se guarda sin error.
        $datos['zonas'] = [$zona('frontal'), $zona('espalda')];
        Livewire::test(ManagePacks::class)->callAction('create', $datos)->assertHasNoFormErrors();
        $this->assertSame([$zona('espalda')], ZonasPack::dePrenda($datos['zonas'], $polo->id));
        $this->assertSame([['prenda' => (string) $polo->nombre, 'si' => ['Espalda grande'], 'no' => ['Frontal']]],
            ZonasPack::resumen($datos['zonas'], [$polo->id]));
    }

    public function test_el_simulador_muestra_el_calculo_del_motor(): void
    {
        $this->seed(EjemplosPreciosSeeder::class);
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));
        $camiseta = Prenda::where('slug->es', 'camiseta-essential')->sole();

        Livewire::test(SimuladorPrecios::class)
            ->fillForm([
                'lineas' => [[
                    'prenda_id' => $camiseta->id, 'color_id' => Color::where('nombre->es', 'Negro')->value('id'),
                    'zonas' => [$camiseta->zonas()->where('clave', 'frontal')->value('zonas_impresion.id')],
                    'tallas' => [['talla_id' => Talla::where('nombre', 'M')->value('id'), 'unidades' => 14]],
                ]],
                'cp' => '28001',
            ])
            ->callAction('calcular')
            ->assertSee('No se puede confirmar.')
            ->assertSee('El mínimo para el pedido es de 15 prendas. Llevas 14: te falta 1.')
            ->assertSee('Península');
    }
}
