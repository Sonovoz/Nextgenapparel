<?php

namespace Tests\Feature\Sistema;

use App\Dominio\Asistente\ClaveIa;
use App\Dominio\Asistente\ClienteOpenAi;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Pages\AjustesIa;
use App\Models\Ajuste;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** D227: la clave de OpenAI se escribe en Ajustes → IA, se guarda cifrada y no se vuelve a enseñar; el .env manda. */
class ClaveIaTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_clave_se_guarda_cifrada_en_el_backend_y_la_usa_openai(): void
    {
        config()->set('ia.openai_key', null);
        $this->actingAs($this->usuarioConRol(Rol::Administrador));
        $this->assertFalse(ClaveIa::configurada());

        Livewire::test(AjustesIa::class)->set('data.clave', 'sk-secreta-123')->callAction('guardar')->assertSet('data.clave', null);

        $this->assertSame('sk-secreta-123', ClaveIa::actual());
        $this->assertSame('panel', ClaveIa::origen());
        // En la base solo está cifrada, y ni la página ni el registro de actividad la enseñan.
        $this->assertStringNotContainsString('sk-secreta-123', (string) json_encode(DB::table('ajustes')->get()));
        $this->assertStringNotContainsString('sk-secreta-123', (string) json_encode(DB::table('activity_log')->get()));
        Livewire::test(AjustesIa::class)->assertDontSee('sk-secreta-123')->assertSee('Configurada ✓');

        // Guardar con el campo vacío conserva la clave; OpenAI la recibe.
        Livewire::test(AjustesIa::class)->callAction('guardar');
        Http::fake(['*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Hola']]], 'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1]])]);
        app(ClienteOpenAi::class)->chat([['role' => 'user', 'content' => 'Hola']]);
        Http::assertSent(fn ($peticion) => $peticion->hasHeader('Authorization', 'Bearer sk-secreta-123'));

        // La del .env manda sobre la del panel; «Borrar la clave» la quita del panel.
        config()->set('ia.openai_key', 'sk-del-env');
        $this->assertSame(['sk-del-env', 'env'], [ClaveIa::actual(), ClaveIa::origen()]);
        config()->set('ia.openai_key', null);
        Livewire::test(AjustesIa::class)->callAction('borrar_clave');
        $this->assertNull(ClaveIa::actual());
        $this->assertNull(Ajuste::obtener(ClaveIa::AJUSTE));
    }
}
