<?php

namespace App\Console;

use App\Jobs\ActualizarContribuyentes;
use App\Jobs\EnviarDocumentoSII;
use App\Models\Contribuyente;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        $schedule->call(function(){
            ActualizarContribuyentes::dispatch()->onQueue('sincronizacion');
        })->weekly();
        /* NO FUNCIONA YA QUE HAY QUE PASARLE EL USUARIO QUE HACE EL ENVIO DE LOS DOCUMENTOSVBN
        $schedule->call(function () {
            $contribuyentes = Contribuyente::all();
            foreach($contribuyentes as $ctr){
                EnviarDocumentoSII::dispatch($ctr, 1)->onQueue('envios');
            }
        })->everyMinute();
        */
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
