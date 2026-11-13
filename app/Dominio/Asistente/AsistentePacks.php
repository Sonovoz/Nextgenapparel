<?php

namespace App\Dominio\Asistente;

use App\Dominio\Catalogo\ModoCliente;
use App\Dominio\Catalogo\ZonasPack;
use App\Dominio\Precios\Motor\Cesta;
use App\Dominio\Precios\Motor\LineaCesta;
use App\Dominio\Precios\Motor\MotorPrecios;
use App\Dominio\Precios\RepartoPack;
use App\Models\ConsumoIa;
use App\Models\Pack;
use App\Models\Prenda;
use App\Models\Talla;
use App\Models\Variante;
use App\Models\ZonaImpresion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Asistente de packs (D233). El equipo describe el pack que quiere y la IA propone la composición con el
 * catálogo real (prendas, colores, tallas, zonas), los textos en español e inglés, los modos y un **porcentaje**
 * de descuento. La IA no calcula importes (D08): el precio de catálogo de esa composición, el coste y el margen
 * los calcula el motor en PHP, el precio sugerido sale de aplicar ese porcentaje y el equipo decide el precio
 * final antes de crear el pack (inactivo).
 */
final class AsistentePacks
{
    public const MAX_LINEAS = 6;

    public const MAX_DESCUENTO = 60;

    public function __construct(private readonly ClienteOpenAi $openAi) {}

    /** @return array<string, mixed> la propuesta ya comprobada (normalizar) */
    public function proponer(string $descripcion): array
    {
        if (! RedactorSeo::disponible()) {
            throw new IaNoDisponible('La IA no está disponible (clave, límite del mes o caída).');
        }
        $sistema = 'Diseñas packs (ofertas cerradas) para una tienda española de prendas personalizadas con impresión DTI. '
            .'Con el catálogo que te paso y lo que pide el equipo, devuelve SOLO un JSON con: "nombre": {"es", "en"} (corto y atractivo), '
            .'"descripcion": {"es", "en"} (una o dos frases, sin precios ni promesas que no estén en el catálogo), "modos": [de '.implode(', ', array_map(fn ($m) => $m->value, ModoCliente::cases())).'], '
            .'"reparto": "libre" (el cliente reparte tallas y elige color) o "fijo" (tallas y color cerrados), "max_disenos": 1, 2 o 3, "zonas": [ids de zona], '
            .'"lineas": [{"prenda_id", "unidades", "colores": [ids], "tallas": [ids], "reparto_tallas": {talla_id: unidades} solo si es fijo}], '
            .'"descuento_pct": número de 0 a '.self::MAX_DESCUENTO.' (cuánto más barato que comprar lo mismo suelto), "motivo": una frase. '
            .'Usa solo ids del catálogo. No escribas ningún importe en euros: los calcula la tienda. Castellano de España e inglés natural.';
        try {
            $r = $this->openAi->chat([
                ['role' => 'system', 'content' => $sistema],
                ['role' => 'user', 'content' => 'Catálogo: '.json_encode(self::catalogo(), JSON_UNESCAPED_UNICODE)."\nLo que pide el equipo: ".mb_substr($descripcion, 0, 2000)],
            ], json: true);
        } catch (IaNoDisponible $e) {
            Cache::put('ia.caida', true, now()->addMinutes(10));

            throw $e;
        }
        ConsumoIa::create(['uso' => 'packs', 'tokens_entrada' => $r['entrada'], 'tokens_salida' => $r['salida'], 'coste' => AsistenteVenta::coste($r['entrada'], $r['salida'])]);
        $json = json_decode((string) ($r['mensaje']['content'] ?? ''), true);
        if (! is_array($json)) {
            throw new IaNoDisponible('La IA no ha devuelto una propuesta válida.');
        }

        return self::normalizar($json);
    }

    /**
     * Catálogo que ve la IA: prendas activas con colores y tallas a la venta y zonas que ofrecen. Sin precios.
     *
     * @return list<array<string, mixed>>
     */
    public static function catalogo(): array
    {
        return Prenda::where('activa', true)->with('familia')->orderBy('id')->get()->map(function (Prenda $p) {
            $variantes = Variante::where('prenda_id', $p->id)->aLaVenta()->with(['color', 'talla'])->get();

            return [
                'prenda_id' => $p->id, 'nombre' => (string) $p->nombre, 'familia' => (string) $p->familia?->nombre,
                'colores' => $variantes->pluck('color')->filter()->unique('id')->map(fn ($c) => ['id' => $c->id, 'nombre' => (string) $c->nombre])->values()->all(),
                'tallas' => $variantes->pluck('talla')->filter()->unique('id')->sortBy('orden')->map(fn ($t) => ['id' => $t->id, 'nombre' => (string) $t->nombre])->values()->all(),
                'zonas' => $p->zonas()->where('zonas_impresion.activa', true)->get()->map(fn (ZonaImpresion $z) => ['id' => $z->id, 'nombre' => (string) $z->nombre])->all(),
            ];
        })->filter(fn (array $p) => $p['colores'] !== [] && $p['tallas'] !== [] && $p['zonas'] !== [])->values()->all();
    }

    /**
     * Comprueba la propuesta contra el catálogo y la deja lista; lo que no vale se descarta y se dice por qué.
     *
     * @param  array<mixed>  $json
     * @return array<string, mixed>
     */
    public static function normalizar(array $json): array
    {
        $catalogo = collect(self::catalogo())->keyBy('prenda_id');
        $descartes = [];
        $texto = fn ($v, int $max) => mb_substr(trim((string) $v), 0, $max);
        $reparto = ($json['reparto'] ?? '') === RepartoPack::Fijo->value ? RepartoPack::Fijo : RepartoPack::Libre;

        $lineas = [];
        foreach (array_slice((array) ($json['lineas'] ?? []), 0, self::MAX_LINEAS) as $l) {
            $prenda = is_array($l) ? $catalogo->get((int) ($l['prenda_id'] ?? 0)) : null;
            $unidades = is_array($l) ? (int) ($l['unidades'] ?? 0) : 0;
            if ($prenda === null || $unidades < 1 || $unidades > 5000) {
                $descartes[] = 'Una prenda que no está en el catálogo o sin unidades válidas.';

                continue;
            }
            $coloresValidos = array_column($prenda['colores'], 'id');
            $tallasValidas = array_column($prenda['tallas'], 'id');
            $colores = array_values(array_intersect(array_map('intval', (array) ($l['colores'] ?? [])), $coloresValidos)) ?: [$coloresValidos[0]];
            $tallas = array_values(array_intersect(array_map('intval', (array) ($l['tallas'] ?? [])), $tallasValidas)) ?: $tallasValidas;
            $fijas = collect((array) ($l['reparto_tallas'] ?? []))->mapWithKeys(fn ($u, $t) => [(int) $t => (int) $u])
                ->filter(fn ($u, $t) => $u > 0 && in_array($t, $tallasValidas, true))->all();
            $lineas[] = ['prenda_id' => $prenda['prenda_id'], 'prenda' => $prenda['nombre'], 'unidades' => $unidades,
                'colores' => $reparto === RepartoPack::Fijo ? [$colores[0]] : $colores, 'tallas' => $tallas, 'reparto_tallas' => $fijas];
        }
        // Un reparto fijo que no suma las unidades no vale: se deja libre (el cliente reparte).
        if ($reparto === RepartoPack::Fijo && collect($lineas)->contains(fn ($l) => array_sum($l['reparto_tallas']) !== $l['unidades'])) {
            $reparto = RepartoPack::Libre;
            $descartes[] = 'El reparto de tallas no sumaba las unidades: el pack queda con tallas a elegir por el cliente.';
        }

        $zonasPedidas = array_map('intval', (array) ($json['zonas'] ?? []));
        $zonas = array_values(array_filter($zonasPedidas, fn (int $z) => collect($lineas)->contains(fn ($l) => in_array($z, array_column($catalogo[$l['prenda_id']]['zonas'], 'id'), true))));
        // D223: cada prenda tiene que llevar al menos una zona del pack.
        $lineas = array_values(array_filter($lineas, function (array $l) use ($zonas, &$descartes) {
            if (ZonasPack::dePrenda($zonas, $l['prenda_id']) === []) {
                $descartes[] = "{$l['prenda']}: no ofrece ninguna de las zonas del pack.";

                return false;
            }

            return true;
        }));

        return [
            'nombre' => ['es' => $texto($json['nombre']['es'] ?? '', 80) ?: 'Pack', 'en' => $texto($json['nombre']['en'] ?? '', 80)],
            'descripcion' => ['es' => $texto($json['descripcion']['es'] ?? '', 300), 'en' => $texto($json['descripcion']['en'] ?? '', 300)],
            'modos' => array_values(array_intersect((array) ($json['modos'] ?? []), array_map(fn ($m) => $m->value, ModoCliente::cases()))),
            'reparto' => $reparto->value,
            'max_disenos' => max(1, min(3, (int) ($json['max_disenos'] ?? 1))),
            'zonas' => $zonas,
            'lineas' => $lineas,
            'descuento_pct' => max(0, min(self::MAX_DESCUENTO, (int) round((float) ($json['descuento_pct'] ?? 0)))),
            'motivo' => $texto($json['motivo'] ?? '', 300),
            'descartes' => $descartes,
        ];
    }

    /**
     * Lo que calcula la tienda (nunca la IA): precio de catálogo de esa composición con el motor (sin IVA, con las
     * ofertas por cantidad), coste de prendas e impresión, precio sugerido con el descuento propuesto y margen.
     *
     * @param  array<string, mixed>  $propuesta
     * @return array{catalogo: int, coste: int, sugerido: int, margen: int, margen_pct: int, unidades: int}
     */
    public static function valorar(array $propuesta, ?int $precio = null): array
    {
        $cesta = [];
        $coste = 0;
        $unidadesTotal = 0;
        foreach ($propuesta['lineas'] as $l) {
            $zonas = ZonasPack::dePrenda($propuesta['zonas'], $l['prenda_id']);
            $tallas = $propuesta['reparto'] === RepartoPack::Fijo->value && $l['reparto_tallas'] !== [] ? $l['reparto_tallas'] : self::repartir($l['tallas'], $l['unidades']);
            $color = (int) $l['colores'][0];
            $cesta[] = new LineaCesta($l['prenda_id'], $color, $tallas, $zonas);
            $variantes = Variante::where('prenda_id', $l['prenda_id'])->where('color_id', $color)->whereIn('talla_id', array_keys($tallas))->get()->keyBy('talla_id');
            foreach ($tallas as $talla => $unidades) {
                $coste += (int) ($variantes->get($talla)->coste ?? 0) * $unidades;
            }
            $coste += (int) DB::table('prenda_zona')->where('prenda_id', $l['prenda_id'])->whereIn('zona_impresion_id', $zonas)->sum('coste') * $l['unidades'];
            $unidadesTotal += $l['unidades'];
        }
        $catalogo = $cesta === [] ? 0 : app(MotorPrecios::class)->calcular(new Cesta($cesta))->subtotal();
        // Precio sugerido: el de catálogo menos el porcentaje propuesto, redondeado al euro (lo decide el equipo).
        $sugerido = (int) (round($catalogo * (100 - (int) $propuesta['descuento_pct']) / 100 / 100) * 100);
        $final = $precio ?? $sugerido;

        return ['catalogo' => $catalogo, 'coste' => $coste, 'sugerido' => $sugerido, 'margen' => $final - $coste,
            'margen_pct' => $final > 0 ? (int) round(($final - $coste) / $final * 100) : 0, 'unidades' => $unidadesTotal];
    }

    /**
     * Crea el pack aprobado, inactivo salvo que se pida, con el precio que ha decidido el equipo (céntimos, sin IVA).
     *
     * @param  array<string, mixed>  $propuesta
     */
    public static function crear(array $propuesta, int $precio, bool $activo = false): Pack
    {
        $p = self::normalizar($propuesta);

        return DB::transaction(function () use ($p, $precio, $activo): Pack {
            $slug = fn (string $texto, string $idioma) => self::slugLibre(Str::slug($texto ?: 'pack'), $idioma);
            $pack = Pack::create([
                'nombre' => array_filter($p['nombre']), 'descripcion' => array_filter($p['descripcion']),
                'slug' => ['es' => $slug($p['nombre']['es'], 'es'), 'en' => $slug($p['nombre']['en'] ?: $p['nombre']['es'], 'en')],
                'precio' => max(0, $precio), 'reparto' => $p['reparto'], 'max_disenos' => $p['max_disenos'],
                'modos' => $p['modos'] ?: null, 'activo' => $activo,
            ]);
            foreach ($p['lineas'] as $l) {
                $pack->lineas()->create([
                    'prenda_id' => $l['prenda_id'], 'unidades' => $l['unidades'], 'colores' => $l['colores'],
                    'tallas_permitidas' => $p['reparto'] === RepartoPack::Libre->value ? $l['tallas'] : null,
                    'reparto_tallas' => $p['reparto'] === RepartoPack::Fijo->value ? $l['reparto_tallas'] : null,
                ]);
            }
            $pack->zonas()->sync($p['zonas']);

            return $pack;
        });
    }

    /**
     * Reparte las unidades entre las tallas (para valorar un pack libre): igual entre todas, el resto en las centrales.
     *
     * @param  list<int>  $tallas
     * @return array<int, int>
     */
    private static function repartir(array $tallas, int $unidades): array
    {
        $tallas = Talla::whereIn('id', $tallas)->orderBy('orden')->pluck('id')->all() ?: $tallas;
        $n = count($tallas);
        $base = intdiv($unidades, $n);
        $reparto = array_fill_keys($tallas, $base);
        $resto = $unidades - $base * $n;
        for ($i = 0; $i < $resto; $i++) {
            $reparto[$tallas[intdiv($n - $resto, 2) + $i]]++;
        }

        return array_filter($reparto);
    }

    private static function slugLibre(string $slug, string $idioma): string
    {
        $base = $slug;
        $n = 2;
        while (Pack::withTrashed()->where("slug->{$idioma}", $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }
}
