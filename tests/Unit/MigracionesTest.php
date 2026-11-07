<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * La MariaDB de XAMPP trae explicit_defaults_for_timestamp desactivado: una
 * segunda columna TIMESTAMP obligatoria sin valor por defecto rompe la
 * migración («Invalid default value»). Para fechas obligatorias se usa
 * dateTime(); timestamp() solo si es nullable() o lleva useCurrent().
 */
class MigracionesTest extends TestCase
{
    public function test_ninguna_migracion_usa_timestamp_obligatorio_sin_valor_por_defecto(): void
    {
        $problemas = [];

        foreach (glob(dirname(__DIR__, 2).'/database/migrations/*.php') ?: [] as $archivo) {
            foreach (file($archivo) ?: [] as $numero => $linea) {
                if (preg_match('/->timestamp\(\s*[\'"]/', $linea)
                    && ! preg_match('/->(nullable|useCurrent)\(/', $linea)) {
                    $problemas[] = basename($archivo).':'.($numero + 1).'  '.trim($linea);
                }
            }
        }

        $this->assertSame([], $problemas, "Usa dateTime() o añade nullable()/useCurrent():\n".implode("\n", $problemas));
    }
}
