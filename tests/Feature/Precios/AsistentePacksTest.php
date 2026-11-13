<?php

namespace Tests\Feature\Precios;

use App\Dominio\Asistente\AsistentePacks;
use App\Dominio\Precios\Motor\Cesta;
use App\Dominio\Precios\Motor\LineaCesta;
use App\Dominio\Precios\Motor\MotorPrecios;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Resources\Precios\Pages\ManagePacks;
use App\Models\Color;
use App\Models\ConsumoIa;
use App\Models\Pack;
use App\Models\Prenda;
use App\Models\Talla;
use App\Models\ZonaImpresion;
use Database\Seeders\EjemplosCatalogoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** D233: la IA propone la composición y el porcentaje; el motor calcula catálogo, coste y margen; el equipo fija el precio. */
class AsistentePacksTest extends TestCase
{
    use RefreshDatabase;

    private Prenda $camiseta;

    private Prenda $polo;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(EjemplosCatalogoSeeder::class);
        Filament::setCurrentPanel('admin');
        config()->set('ia.openai_key', 'sk-prueba');
        $this->camiseta = Prenda::where('slug->es', 'camiseta-essential')->sole();
        $this->polo = Prenda::where('slug->es', 'polo-classic')->sole();
    }

    private function zona(string $clave): int
    {
        return (int) ZonaImpresion::where('clave', $clave)->value('id');
    }

    /** @return array<string, mixed> */
    private function respuestaIa(): array
    {
        $negro = (int) Color::where('nombre->es', 'Negro')->value('id');

        return [
            'nombre' => ['es' => 'Pack Festival', 'en' => 'Festival Pack'], 'descripcion' => ['es' => 'Para tu evento.', 'en' => 'For your event.'],
            'modos' => ['eventos', 'inventado'], 'reparto' => 'libre', 'max_disenos' => 2, 'zonas' => [$this->zona('frontal'), $this->zona('espalda'), 9999],
            'lineas' => [
                ['prenda_id' => $this->camiseta->id, 'unidades' => 30, 'colores' => [$negro, 9999], 'tallas' => Talla::whereIn('nombre', ['M', 'L'])->pluck('id')->all()],
                ['prenda_id' => $this->polo->id, 'unidades' => 10, 'colores' => [$negro], 'tallas' => []], // el polo no tiene frontal: lleva la espalda
                ['prenda_id' => 999999, 'unidades' => 5],
            ],
            'descuento_pct' => 15, 'motivo' => 'Un 15 % menos que suelto.',
        ];
    }

    public function test_la_propuesta_se_comprueba_contra_el_catalogo(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => json_encode($this->respuestaIa())]]], 'usage' => ['prompt_tokens' => 2000, 'completion_tokens' => 400]])]);

        $p = app(AsistentePacks::class)->proponer('Pack para festivales con camisetas y polos negros, delante y detrás.');

        $this->assertSame(['eventos'], $p['modos']);
        $this->assertSame([$this->zona('frontal'), $this->zona('espalda')], $p['zonas']);
        $this->assertCount(2, $p['lineas']); // la prenda inventada se descarta
        $this->assertSame([(int) Color::where('nombre->es', 'Negro')->value('id')], $p['lineas'][0]['colores']);
        $this->assertNotEmpty($p['lineas'][1]['tallas']); // sin tallas: todas las del polo
        $this->assertSame(15, $p['descuento_pct']);
        $this->assertSame(1, ConsumoIa::where('uso', 'packs')->count());
        // A la IA no se le pasan precios.
        Http::assertSent(fn ($r) => ! str_contains(json_encode($r->data()), 'precio_base'));
    }

    public function test_el_motor_calcula_el_catalogo_el_coste_y_el_sugerido_y_se_crea_con_el_precio_del_equipo(): void
    {
        $p = AsistentePacks::normalizar($this->respuestaIa());
        $v = AsistentePacks::valorar($p);

        // Catálogo = el motor con la misma composición (tallas repartidas, cada prenda con sus zonas).
        $negro = (int) Color::where('nombre->es', 'Negro')->value('id');
        [$m, $l] = [Talla::where('nombre', 'M')->value('id'), Talla::where('nombre', 'L')->value('id')];
        $tallasPolo = $p['lineas'][1]['tallas'];
        $esperado = app(MotorPrecios::class)->calcular(new Cesta([
            new LineaCesta($this->camiseta->id, $negro, [$m => 15, $l => 15], [$this->zona('frontal'), $this->zona('espalda')]),
            new LineaCesta($this->polo->id, $negro, array_fill_keys($tallasPolo, 2), [$this->zona('espalda')]), // 10 polos en 5 tallas
        ]))->subtotal();
        $this->assertSame($esperado, $v['catalogo']);
        $this->assertSame((int) (round($esperado * 0.85 / 100) * 100), $v['sugerido']);
        $this->assertGreaterThan(0, $v['coste']);
        $this->assertSame(40, $v['unidades']);

        $pack = AsistentePacks::crear($p, 25000);
        $this->assertFalse($pack->activo);
        $this->assertSame([25000, 'libre', 2, ['eventos']], [$pack->precio, $pack->reparto->value, $pack->max_disenos, $pack->modos]);
        $this->assertSame('pack-festival', $pack->getTranslation('slug', 'es'));
        $this->assertCount(2, $pack->lineas);
        $this->assertSame(25000 - $v['coste'], AsistentePacks::valorar($p, 25000)['margen']);
        // Un segundo pack con el mismo nombre no repite la dirección.
        $this->assertSame('pack-festival-2', AsistentePacks::crear($p, 25000)->getTranslation('slug', 'es'));
        $this->assertSame(2, Pack::count());
    }

    public function test_el_boton_solo_sale_con_la_ia_y_la_vista_previa_ensena_las_cifras_del_motor(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::Superadministrador));
        Livewire::test(ManagePacks::class)->assertActionVisible('ia')->mountAction('ia')->assertActionMounted('ia');
        config()->set('ia.openai_key', null);
        Livewire::test(ManagePacks::class)->assertActionHidden('ia');

        $vista = (string) ManagePacks::vistaPrevia($this->respuestaIa(), 25000);
        $this->assertStringContainsString('Catálogo (motor)', $vista);
        $this->assertStringContainsString('Sugerido (−15 %)', $vista);
        $this->assertStringContainsString('10 × Polo Classic', $vista);
        $this->assertStringContainsString('la IA solo propone', $vista);
    }
}
