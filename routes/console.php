<?php

use App\Dominio\Sistema\Salud\Latido;
use App\Jobs\LatidoCola;
use App\Models\RegistroError;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tareas programadas (Herramientas → Estado del sistema)
|--------------------------------------------------------------------------
|
| En el servidor las ejecuta el contenedor «scheduler». En local hay que
| dejar abierta una ventana con: php artisan schedule:work
|
*/

// Latidos: demuestran que las tareas programadas y la cola funcionan.
Schedule::call(fn () => Latido::registrar(Latido::TAREAS))
    ->name('latido-tareas')
    ->description('Latido de las tareas programadas')
    ->everyMinute();

Schedule::job(new LatidoCola)
    ->name('latido-cola')
    ->description('Latido de la cola')
    ->everyMinute();

// En local no hay contenedor de cola: la propia tarea programada la procesa.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->description('Procesar la cola (solo en local)')
    ->everyMinute()
    ->withoutOverlapping()
    ->environments(['local']);

// Comprobación de salud con aviso por correo si algo falla.
Schedule::command('nga:comprobar-salud')
    ->description('Comprobar la salud del sistema')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Limpiezas nocturnas.
Schedule::command('model:prune', ['--model' => [RegistroError::class]])
    ->description('Borrar errores resueltos hace más de 90 días')
    ->dailyAt('03:10');

Schedule::command('activitylog:clean --force')
    ->description('Borrar actividad de hace más de un año')
    ->dailyAt('03:20');

Schedule::command('queue:prune-failed --hours=720')
    ->description('Borrar trabajos fallidos de hace más de 30 días')
    ->dailyAt('03:30');

Schedule::command('disenos:limpiar')
    ->description('Borrar diseños sin pedido de visitantes sin cuenta y archivos huérfanos')
    ->dailyAt('03:40')
    ->withoutOverlapping();

// Copias de seguridad (paso 1.6): limpieza según la retención, copia diaria
// y prueba de restauración semanal.
Schedule::command('backup:clean --disable-notifications')
    ->description('Borrar copias antiguas (7 diarias, 4 semanales, 12 mensuales)')
    ->dailyAt('01:00');

Schedule::command('nga:copia-seguridad')
    ->description('Copia de seguridad de la base de datos y los archivos')
    ->dailyAt('01:30')
    ->withoutOverlapping();

Schedule::command('nga:copia-seguridad --probar')
    ->description('Probar que la última copia se puede restaurar')
    ->weeklyOn(0, '04:00');
