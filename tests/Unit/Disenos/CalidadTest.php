<?php

namespace Tests\Unit\Disenos;

use App\Dominio\Disenos\Calidad;
use App\Dominio\Disenos\NivelCalidad;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CalidadTest extends TestCase
{
    private const UMBRALES = ['correcto' => 300, 'aceptable' => 200, 'minimo' => 150];

    /** @return array<string, array{int, int, int}> */
    public static function casosPpp(): array
    {
        return [
            '3307 px a 28 cm = 300 ppp' => [3307, 280, 300],
            '1000 px a 25,4 cm = 100 ppp' => [1000, 254, 100],
            '1063 px a 9 cm = 300 ppp' => [1063, 90, 300],
            'redondea al más cercano (299,7)' => [1062, 90, 300],
            'redondea hacia abajo (199,4)' => [785, 100, 199],
        ];
    }

    #[DataProvider('casosPpp')]
    public function test_el_ppp_efectivo_es_pixeles_entre_pulgadas(int $px, int $mm, int $ppp): void
    {
        $this->assertSame($ppp, Calidad::pppEfectivo($px, $mm));
    }

    /** @return array<string, array{int, NivelCalidad}> */
    public static function casosNivel(): array
    {
        return [
            '300 es correcta' => [300, NivelCalidad::Correcta],
            '299 es buena' => [299, NivelCalidad::Buena],
            '200 es buena' => [200, NivelCalidad::Buena],
            '199 es justa' => [199, NivelCalidad::Justa],
            '150 es justa' => [150, NivelCalidad::Justa],
            '149 es baja' => [149, NivelCalidad::Baja],
        ];
    }

    #[DataProvider('casosNivel')]
    public function test_los_umbrales_marcan_el_nivel(int $ppp, NivelCalidad $nivel): void
    {
        $this->assertSame($nivel, Calidad::nivel($ppp, self::UMBRALES));
    }

    public function test_se_evalua_por_el_lado_que_peor_queda(): void
    {
        // 3000 × 1000 px a 28 × 20 cm: ancho 272 ppp, alto 127 ppp → baja.
        $this->assertSame(['ppp' => 127, 'nivel' => NivelCalidad::Baja], Calidad::evaluar(3000, 1000, 280, 200, self::UMBRALES));
    }

    public function test_tamano_maximo_para_un_ppp(): void
    {
        $this->assertSame(254, Calidad::tamanoMaximoMm(3000, 300)); // 3000 px a 300 ppp = 25,4 cm
    }

    public function test_valores_no_positivos_se_rechazan(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Calidad::pppEfectivo(1000, 0);
    }
}
