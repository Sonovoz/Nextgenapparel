<?php

namespace App\Console\Commands;

use App\Dominio\Sistema\Copias\Copias;
use Illuminate\Console\Command;

/**
 * php artisan nga:copia-seguridad            Hace una copia completa.
 * php artisan nga:copia-seguridad --probar   Prueba que la última se puede restaurar.
 */
class CopiaSeguridadCommand extends Command
{
    protected $signature = 'nga:copia-seguridad {--probar : Prueba la restauración de la última copia en lugar de hacer una}';

    protected $description = 'Hace una copia de seguridad (base de datos y archivos) o prueba la última';

    public function handle(Copias $copias): int
    {
        if ($this->option('probar')) {
            $resultado = $copias->probarRestauracion();
            $resultado['correcto'] ? $this->components->info($resultado['mensaje']) : $this->components->error($resultado['mensaje']);

            return $resultado['correcto'] ? self::SUCCESS : self::FAILURE;
        }

        $error = $copias->hacer();

        if ($error !== null) {
            $this->components->error('La copia ha fallado: '.$error);

            return self::FAILURE;
        }

        $ultima = Copias::listar()[0] ?? null;
        $this->components->info('Copia hecha'.($ultima ? ': '.$ultima['nombre'].' ('.number_format($ultima['bytes'] / 1024, 0, ',', '.').' KB).' : '.'));

        return self::SUCCESS;
    }
}
