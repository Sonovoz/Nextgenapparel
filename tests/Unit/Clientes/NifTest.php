<?php

namespace Tests\Unit\Clientes;

use App\Dominio\Clientes\Nif;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NifTest extends TestCase
{
    public static function validos(): array
    {
        return [
            'DNI' => ['12345678Z', 'dni'],
            'DNI con ceros' => ['00000001R', 'dni'],
            'DNI con espacios, guion y minúscula' => [' 12.345.678-z ', 'dni'],
            'NIE X' => ['X1234567L', 'nie'],
            'NIE Y' => ['Y7654321G', 'nie'],
            'NIE Z' => ['Z1111111D', 'nie'],
            'CIF sociedad limitada (control cifra)' => ['B12345674', 'cif'],
            'CIF sociedad anónima' => ['A58818501', 'cif'],
            'CIF organismo público (control letra)' => ['P2800000H', 'cif'],
            'CIF entidad extranjera (letra)' => ['N1234567D', 'cif'],
        ];
    }

    #[DataProvider('validos')]
    public function test_acepta_dni_nie_y_cif_con_su_control(string $nif, string $tipo): void
    {
        $this->assertTrue(Nif::valido($nif));
        $this->assertSame($tipo, Nif::tipo($nif));
    }

    public static function invalidos(): array
    {
        return [
            'letra de DNI equivocada' => ['12345678A'],
            'DNI corto' => ['1234567Z'],
            'NIE con letra equivocada' => ['X1234567A'],
            'CIF con control equivocado' => ['B12345675'],
            'CIF de sociedad con letra de control' => ['B1234567D'],
            'CIF de organismo con cifra de control' => ['P28000008'],
            'letra inicial inexistente' => ['I1234567J'],
            'vacío' => [''],
            'texto' => ['no-es-un-nif'],
        ];
    }

    #[DataProvider('invalidos')]
    public function test_rechaza_lo_que_no_cuadra(string $nif): void
    {
        $this->assertFalse(Nif::valido($nif));
        $this->assertNull(Nif::tipo($nif));
    }
}
