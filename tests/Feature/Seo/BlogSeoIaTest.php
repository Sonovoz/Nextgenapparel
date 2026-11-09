<?php

namespace Tests\Feature\Seo;

use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Resources\Blog\EntradaBlogResource;
use App\Filament\Resources\Catalogo\Prendas\Pages\EditPrenda;
use App\Models\ConsumoIa;
use App\Models\EntradaBlog;
use App\Models\Familia;
use App\Models\Prenda;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** Asistente SEO con IA y blog (v2, D195), con OpenAI simulado. */
class BlogSeoIaTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_ia_propone_el_seo_y_la_traduccion_sin_guardar_nada(): void
    {
        config()->set('ia.openai_key', 'sk-prueba');
        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => json_encode([
                'seo_titulo' => ['es' => 'Camiseta Essential personalizada', 'en' => 'Custom Essential T-shirt'],
                'seo_descripcion' => ['es' => 'Algodón orgánico con tu diseño.', 'en' => 'Organic cotton with your design.'],
                'traducciones' => ['descripcion' => 'Organic cotton T-shirt.', 'inventado' => 'no'],
            ])]]],
            'usage' => ['prompt_tokens' => 300, 'completion_tokens' => 80],
        ])]);
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));
        $prenda = Prenda::factory()->for(Familia::factory()->create())->create(['nombre' => ['es' => 'Camiseta Essential'], 'descripcion' => ['es' => 'Camiseta de algodón orgánico.']]);

        Livewire::test(EditPrenda::class, ['record' => $prenda->getRouteKey()])
            ->callAction('seo_ia')
            ->assertSet('data.seo_titulo.es', 'Camiseta Essential personalizada')
            ->assertSet('data.seo_descripcion.en', 'Organic cotton with your design.')
            ->assertSet('data.descripcion.en', 'Organic cotton T-shirt.');

        $this->assertNull($prenda->fresh()->getTranslation('seo_titulo', 'es', false) ?: null); // solo propone
        $this->assertSame('seo', ConsumoIa::sole()->uso);
        Http::assertSent(fn ($p) => $p['response_format'] === ['type' => 'json_object'] && str_contains($p['messages'][1]['content'], 'algodón orgánico'));
    }

    public function test_sin_clave_no_aparece_la_accion(): void
    {
        config()->set('ia.openai_key', '');
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));
        $prenda = Prenda::factory()->for(Familia::factory()->create())->create();

        Livewire::test(EditPrenda::class, ['record' => $prenda->getRouteKey()])->assertActionHidden('seo_ia');
    }

    public function test_el_blog_muestra_solo_lo_publicado_con_su_seo(): void
    {
        $publicada = EntradaBlog::create(['titulo' => ['es' => 'Cómo preparar tu PNG', 'en' => 'How to prepare your PNG'], 'slug' => ['es' => 'preparar-png', 'en' => 'prepare-png'],
            'extracto' => ['es' => 'Guía rápida.'], 'contenido' => ['es' => "## Resolución\n\n300 ppp <script>x</script>"], 'seo_titulo' => ['es' => 'Preparar un PNG para DTI'],
            'seo_descripcion' => ['es' => 'Resolución y transparencia.'], 'publicada_en' => now()->subDay()]);
        EntradaBlog::create(['titulo' => ['es' => 'Borrador secreto'], 'slug' => ['es' => 'borrador'], 'contenido' => ['es' => 'x']]);

        $this->get(route('blog', ['idioma' => 'es']))->assertOk()->assertSee('Cómo preparar tu PNG')->assertDontSee('Borrador secreto');
        $this->get(route('blog.ver', ['idioma' => 'es', 'slug' => 'preparar-png']))->assertOk()
            ->assertSee('<title>Preparar un PNG para DTI', false)->assertSee('<h2>Resolución</h2>', false)->assertDontSee('<script>x', false);
        $this->get(route('blog.ver', ['idioma' => 'en', 'slug' => 'prepare-png']))->assertOk()->assertSee('How to prepare your PNG');
        $this->get(route('blog.ver', ['idioma' => 'es', 'slug' => 'borrador']))->assertNotFound();
        $this->get('/sitemap-es.xml')->assertSee(route('blog.ver', ['idioma' => 'es', 'slug' => $publicada->getTranslation('slug', 'es')]))->assertDontSee('/borrador');
    }

    public function test_el_blog_lo_gestiona_quien_tiene_marketing(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda))->get(EntradaBlogResource::getUrl('index', panel: 'admin'))->assertOk();
        $this->actingAs($this->usuarioConRol(Rol::Produccion))->get(EntradaBlogResource::getUrl('index', panel: 'admin'))->assertForbidden();
    }
}
