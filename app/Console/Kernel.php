<?php

namespace App\Console;

use App\Jobs\ActualizarContribuyentes;
use App\Jobs\EnviarDocumentoSII;
use App\Jobs\GenerarRCOF;
use App\Models\Contribuyente;
use App\Models\RCOF;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

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
        // Se envia el RCOF de los contribuyentes una vez al dia, en este caso, a las 02 AM para que no existan problemas.
        $schedule->call(function(){
            $contribuyentes = Contribuyente::all();
            foreach($contribuyentes as $contribuyente){
            //    if($contribuyente->boleta_produccion == true){
                    Log::info("Se pone en cola generar RCOF del contribuyente ".$contribuyente->rut);
                    $rcof = RCOF::where('ref_contribuyente', $contribuyente->id)->where('fecha', date('Y-m-d', strtotime('-1 day')))->first();
                    if($rcof == null){
                        Log::info("Se pone en cola generar RCOF del contribuyente ".$contribuyente->rut. ' ya que no se genero correctamente.');
                        $usuario = $contribuyente->users[0];
                        if($usuario != null){
                            GenerarRCOF::dispatch($contribuyente, $usuario)->onQueue('documento');
                            Contribuyente::where('id', $contribuyente->id)->update([
                                'contador_boletas' => $contribuyente->contador_boletas + 5,
                            ]);
                        }
                    }
            //    }
            }
        })->dailyAt('02:00');
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
