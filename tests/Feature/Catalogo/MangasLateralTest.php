<?php

namespace Tests\Feature\Catalogo;

use App\Dominio\Catalogo\Cara;
use App\Dominio\Catalogo\OtrasPrendas;
use App\Dominio\Catalogo\Proveedores\ImportarTopTex;
use App\Dominio\Catalogo\TipoFoto;
use App\Dominio\Catalogo\ZonasPorDefecto;
use App\Models\Foto;
use App\Models\Prenda;
use App\Models\ZonaImpresion;
use Database\Seeders\EjemplosCatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** D257: mangas derecha e izquierda, en las vistas laterales de la prenda. */
class MangasLateralTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_manga_se_imprime_en_la_foto_lateral_y_el_visor_ofrece_esa_vista(): void
    {
        Storage::fake(Foto::DISCO);
        $this->seed(EjemplosCatalogoSeeder::class);
        $camiseta = Prenda::where('slug->es', 'camiseta-essential')->sole();
        $negro = $camiseta->variantes()->with('color')->get()->firstWhere('color.codigo_hex', '#1C1C1F')->color;
        $manga = ZonaImpresion::where('clave', 'manga_derecha')->sole(); // la crea el catálogo inicial, sin activar
        $this->assertSame(Cara::Derecha, $manga->cara);
        $manga->update(['activa' => true]);
        $this->permitirZona($camiseta, $manga, 250);

        // Al subir la foto lateral derecha, la manga se coloca sola en su posición de partida.
        $foto = Foto::create(['prenda_id' => $camiseta->id, 'color_id' => $negro->id, 'tipo' => TipoFoto::Maqueta, 'cara' => Cara::Derecha, 'ruta' => 'catalogo/1/negro-lado.webp']);
        app(ZonasPorDefecto::class)($foto);
        $this->assertSame(ZonasPorDefecto::POSICIONES['manga_derecha'][0], $foto->zonas()->sole()->x);

        $html = $this->get('/es/camisetas/camiseta-essential')->assertOk()
            ->assertSee('data-cara="derecha"', false)->assertSee('Lado der.')->getContent();
        preg_match("/data-colores='([^']+)'/", $html, $coincidencia);
        $negroEnVisor = collect(json_decode(html_entity_decode($coincidencia[1]), true))->firstWhere('nombre', 'Negro');
        $this->assertSame('Manga derecha', $negroEnVisor['caras']['derecha']['zonas'][0]['nombre']);
        $this->assertNull($negroEnVisor['caras']['izquierda']); // sin foto izquierda, ese botón queda oculto
    }

    public function test_la_vista_previa_de_otra_prenda_usa_el_lateral_si_el_diseno_solo_va_en_la_manga(): void
    {
        $prenda = ['caras' => [
            'delantera' => ['url' => 'd.webp', 'zonas' => [['clave' => 'pecho', 'x' => 30, 'y' => 26, 'ancho' => 40, 'alto' => 40]]],
            'trasera' => null,
            'derecha' => ['url' => 'lado.webp', 'zonas' => [['clave' => 'manga_derecha', 'x' => 45, 'y' => 23, 'ancho' => 9, 'alto' => 7]]],
            'izquierda' => null,
        ]];

        $previa = OtrasPrendas::previa($prenda, [['cara' => 'derecha', 'clave' => 'manga_derecha', 'miniatura' => 'logo.png']]);

        $this->assertSame('lado.webp', $previa['foto']);
    }

    public function test_las_mangas_se_crean_sin_activar_y_toptex_trae_sus_fotos_laterales(): void
    {
        DB::table('zonas_impresion')->delete();
        ZonaImpresion::factory()->create(['clave' => 'pecho']);

        (require database_path('migrations/2026_12_15_120000_crear_zonas_de_mangas.php'))->up();

        foreach (['manga_derecha' => Cara::Derecha, 'manga_izquierda' => Cara::Izquierda] as $clave => $cara) {
            $zona = ZonaImpresion::where('clave', $clave)->sole();
            $this->assertSame([$cara, 100, 100, 0, false], [$zona->cara, $zona->ancho_max_mm, $zona->alto_max_mm, $zona->precio, $zona->activa]);
        }

        $this->assertSame(
            ['delantera' => 'https://x/f.png', 'derecha' => 'https://x/r.png', 'izquierda' => 'https://x/l.png'],
            ImportarTopTex::fotos(['FACE' => ['url' => 'https://x/f.png'], 'SIDE_RIGHT' => ['url' => 'https://x/r.png'], 'SIDE_LEFT' => ['url' => 'https://x/l.png']])
        );
    }
}
