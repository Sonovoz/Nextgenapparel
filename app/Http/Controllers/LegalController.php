<?php

namespace App\Http\Controllers;

use App\Dominio\Legal\GenerarTextosLegales;
use App\Dominio\Legal\PaginaLegal;
use App\Models\TextoLegal;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Textos legales en la tienda (paso 8.1, D183): la última versión generada; si aún no hay, se genera. */
class LegalController
{
    public function ver(string $idioma, string $pagina, GenerarTextosLegales $generar): View
    {
        $pagina = PaginaLegal::from($pagina);
        $texto = TextoLegal::actual($pagina, $idioma);
        if ($texto === null) {
            $generar();
            $texto = TextoLegal::actual($pagina, $idioma);
        }
        abort_if($texto === null, 404);

        return view('tienda.legal', [
            'texto' => $texto,
            'html' => Str::markdown($texto->contenido, ['html_input' => 'escape', 'allow_unsafe_links' => false]),
            'alternos' => collect(config('idiomas.disponibles'))->keys()->mapWithKeys(fn ($i) => [$i => route('legal', ['idioma' => $i, 'pagina' => $pagina->value])])->all(),
        ]);
    }
}
