<?php

namespace Tests\Feature\Disenos;

use App\Dominio\Catalogo\Cara;
use App\Dominio\Catalogo\TipoFoto;
use App\Dominio\Disenos\MejoraIa;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Pages\AjustesGenerales;
use App\Models\Ajuste;
use App\Models\Foto;
use App\Models\FotoZona;
use App\Models\Prenda;
use Database\Seeders\EjemplosCatalogoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Instrucciones para que el cliente mejore su diseño con su propia IA (D143). */
class MejoraIaTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('niveles')]
    public function test_se_ofrece_desde_el_nivel_elegido(bool $activa, ?string $desde, array $niveles): void
    {
        Ajuste::guardar('disenos.ia_activa', $activa);
        if ($desde !== null) {
            Ajuste::guardar('disenos.ia_desde', $desde);
        }

        $this->assertSame($niveles, MejoraIa::niveles());
    }

    public static function niveles(): array
    {
        return [
            'por defecto: justa y baja' => [true, null, ['justa', 'baja']],
            'desde buena' => [true, 'buena', ['buena', 'justa', 'baja']],
            'solo baja' => [true, 'baja', ['baja']],
            'valor desconocido: justa' => [true, 'excelente', ['justa', 'baja']],
            'apagada' => [false, 'buena', []],
        ];
    }

    public function test_el_texto_por_defecto_lleva_el_hueco_del_tamano_en_cada_idioma(): void
    {
        foreach (['es' => 'Mi tamaño deseado es: :medida.', 'en' => 'My desired size is: :medida.'] as $idioma => $frase) {
            $this->assertStringContainsString($frase, MejoraIa::texto($idioma));
        }
    }

    public function test_un_texto_propio_sustituye_al_de_por_defecto_solo_en_su_idioma(): void
    {
        Ajuste::guardar('disenos.ia_texto', ['es' => 'Mejóralo a :medida', 'en' => '   ']);

        $this->assertSame('Mejóralo a :medida', MejoraIa::texto('es'));
        $this->assertSame(MejoraIa::porDefecto('en'), MejoraIa::texto('en'));
    }

    public function test_se_configura_en_ajustes_generales_y_solo_guarda_lo_que_cambia(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->usuarioConRol(Rol::Administrador));

        Livewire::test(AjustesGenerales::class)
            ->assertSchemaStateSet(['ia_activa' => true, 'ia_desde' => 'justa', 'ia_texto.es' => MejoraIa::porDefecto('es')])
            ->fillForm(['ia_desde' => 'buena', 'ia_texto.es' => 'Mi texto con :medida', 'ia_texto.en' => MejoraIa::porDefecto('en')])
            ->callAction('guardar')
            ->assertNotified('Ajustes guardados');

        $this->assertSame('buena', Ajuste::obtener('disenos.ia_desde'));
        $this->assertSame(['es' => 'Mi texto con :medida'], Ajuste::obtener('disenos.ia_texto'));
    }

    public function test_la_ficha_lleva_el_boton_y_las_instrucciones_para_el_visor(): void
    {
        Storage::fake(Foto::DISCO);
        $this->seed(EjemplosCatalogoSeeder::class);
        $camiseta = Prenda::where('slug->es', 'camiseta-essential')->sole();
        $foto = Foto::create(['prenda_id' => $camiseta->id, 'color_id' => $camiseta->variantes()->first()->color_id, 'tipo' => TipoFoto::Maqueta, 'cara' => Cara::Delantera, 'ruta' => 'catalogo/1/x.webp']);
        FotoZona::create(['foto_id' => $foto->id, 'zona_impresion_id' => $camiseta->zonas()->first()->id, 'x' => 3000, 'y' => 2600, 'ancho' => 4000, 'alto' => 4200]);
        Ajuste::guardar('disenos.ia_texto', ['es' => 'Mejora el logo para :medida']);

        $this->get('/es/camisetas/camiseta-essential')->assertOk()
            ->assertSee('Mejorar mi diseño con IA')
            ->assertSee('Mejora el logo para :medida', false);
    }
}
