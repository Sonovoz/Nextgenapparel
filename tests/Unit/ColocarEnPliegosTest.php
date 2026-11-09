<?php

namespace Tests\Unit;

use App\Dominio\Produccion\ColocarEnPliegos;
use PHPUnit\Framework\TestCase;

/** Pliegos DTI (v2, D193): film de 580 mm, pliegos de 1000 mm como mucho y 5 mm de separación. */
class ColocarEnPliegosTest extends TestCase
{
    private static function colocar(array $piezas): array
    {
        return ColocarEnPliegos::colocar($piezas, 580, 1000, 5);
    }

    public function test_coloca_en_filas_de_la_mas_alta_a_la_mas_baja_sin_salirse(): void
    {
        $r = self::colocar([
            ['id' => 'a', 'ancho' => 250, 'alto' => 100],
            ['id' => 'b', 'ancho' => 280, 'alto' => 300],
            ['id' => 'c', 'ancho' => 280, 'alto' => 200],
        ]);

        // Fila 1: b (300 alto) y c; fila 2: a, debajo de la más alta.
        $this->assertSame([['b', 5, 5], ['c', 290, 5], ['a', 5, 310]], array_map(fn ($p) => [$p['id'], $p['x'], $p['y']], $r['pliegos'][0]['piezas']));
        $this->assertSame(415, $r['pliegos'][0]['largo']);
        foreach ($r['pliegos'][0]['piezas'] as $p) {
            $this->assertLessThanOrEqual(575, $p['x'] + $p['ancho']);
        }
    }

    public function test_si_no_cabe_abre_otro_pliego_y_gira_lo_que_solo_cabe_girado(): void
    {
        $piezas = array_map(fn ($i) => ['id' => "p{$i}", 'ancho' => 280, 'alto' => 380], range(1, 6)); // 2 por fila, 2 filas por pliego
        $piezas[] = ['id' => 'ancha', 'ancho' => 700, 'alto' => 200];
        $piezas[] = ['id' => 'imposible', 'ancho' => 700, 'alto' => 700];

        $r = self::colocar($piezas);

        $this->assertSame(['imposible'], $r['fuera']);
        // La girada (700 de alto) va primero y ocupa casi un pliego con p1 al lado; el resto, 4 + 1.
        $this->assertSame([['ancha', 'p1'], ['p2', 'p3', 'p4', 'p5'], ['p6']], array_map(fn ($pl) => array_column($pl['piezas'], 'id'), $r['pliegos']));
        $girada = collect($r['pliegos'])->flatMap(fn ($p) => $p['piezas'])->firstWhere('id', 'ancha');
        $this->assertSame([true, 200, 700], [$girada['girada'], $girada['ancho'], $girada['alto']]);
    }

    public function test_sin_piezas_no_hay_pliegos(): void
    {
        $this->assertSame(['pliegos' => [], 'fuera' => []], self::colocar([]));
    }
}
