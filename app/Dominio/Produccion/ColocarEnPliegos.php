<?php

namespace App\Dominio\Produccion;

/**
 * Coloca piezas rectangulares (mm) en pliegos de film DTI (v2, D193): estanterías de izquierda a derecha y de
 * arriba abajo, de la más alta a la más baja, con una separación entre piezas y con los bordes. Una pieza más ancha
 * que el film se gira 90° si así cabe; si no cabe de ninguna forma, queda fuera (y se dice). Determinista.
 */
final class ColocarEnPliegos
{
    /**
     * @param  list<array{id: string, ancho: int, alto: int}>  $piezas  mm
     * @return array{pliegos: list<array{largo: int, piezas: list<array{id: string, x: int, y: int, ancho: int, alto: int, girada: bool}>}>, fuera: list<string>}
     */
    public static function colocar(array $piezas, int $ancho, int $largoMaximo, int $separacion): array
    {
        $util = $ancho - 2 * $separacion;
        $fuera = [];
        $listas = [];
        foreach ($piezas as $p) {
            if ($p['ancho'] <= $util && $p['alto'] <= $largoMaximo - 2 * $separacion) {
                $listas[] = [...$p, 'girada' => false];
            } elseif ($p['alto'] <= $util && $p['ancho'] <= $largoMaximo - 2 * $separacion) {
                $listas[] = ['id' => $p['id'], 'ancho' => $p['alto'], 'alto' => $p['ancho'], 'girada' => true];
            } else {
                $fuera[] = $p['id'];
            }
        }
        // De la más alta a la más baja (a igualdad, la más ancha y luego por id): estanterías compactas y siempre igual.
        usort($listas, fn ($a, $b) => [$b['alto'], $b['ancho'], $a['id']] <=> [$a['alto'], $a['ancho'], $b['id']]);

        $pliegos = [];
        $actual = ['largo' => 0, 'piezas' => []];
        $x = $separacion;
        $y = $separacion;
        $altoFila = 0;
        foreach ($listas as $p) {
            if ($x + $p['ancho'] > $ancho - $separacion) { // fila llena: la siguiente
                $y += $altoFila + $separacion;
                $x = $separacion;
                $altoFila = 0;
            }
            if ($y + $p['alto'] > $largoMaximo - $separacion) { // pliego lleno: uno nuevo
                $pliegos[] = $actual;
                $actual = ['largo' => 0, 'piezas' => []];
                $x = $separacion;
                $y = $separacion;
                $altoFila = 0;
            }
            $actual['piezas'][] = ['id' => $p['id'], 'x' => $x, 'y' => $y, 'ancho' => $p['ancho'], 'alto' => $p['alto'], 'girada' => $p['girada']];
            $actual['largo'] = max($actual['largo'], $y + $p['alto'] + $separacion);
            $x += $p['ancho'] + $separacion;
            $altoFila = max($altoFila, $p['alto']);
        }
        if ($actual['piezas'] !== []) {
            $pliegos[] = $actual;
        }

        return ['pliegos' => $pliegos, 'fuera' => $fuera];
    }
}
