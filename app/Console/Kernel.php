<?php

namespace App\Console;

use App\Helpers\FirmaElectronica;
use App\Jobs\ActualizarContribuyentes;
use App\Jobs\EnviarDocumentoSII;
use App\Jobs\GenerarRCOF;
use App\Models\Contribuyente;
use App\Models\Documento;
use App\Models\RCOF;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;
use SolucionTotal\CoreDTE\Sii;
use SolucionTotal\CoreDTE\Sii\Autenticacion;

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
        /* SE DESACTIVA ESTE JOBS YA QUE NO TIENE UTILIDAD TENER ESA INFORMACIÓN EN LA BD
        $schedule->call(function(){
            ActualizarContribuyentes::dispatch()->onQueue('sincronizacion');
        })->weekly();
        */
        // Se envia el RCOF de los contribuyentes una vez al dia, en este caso, a las 02 AM para que no existan problemas.
        $schedule->call(function(){
            $contribuyentes = Contribuyente::all();
            foreach($contribuyentes as $contribuyente){
                if($contribuyente->boleta_produccion == true){
                    Log::info("Se pone en cola generar RCOF del contribuyente ".$contribuyente->rut);
                    $rcof = RCOF::where('ref_contribuyente', $contribuyente->id)->where('fecha', date('Y-m-d', strtotime('-1 day')))->first();
                    if($rcof == null){
                        Log::info("Se pone en cola generar RCOF del contribuyente ".$contribuyente->rut. ' ya que no se genero correctamente.');
                        $usuarios = $contribuyente->users;
                        $usuario = null;
                        if(count($usuarios) > 0){
                            $usuario = $usuarios[0];
                        }
                        if($usuario != null){
                            \App\Jobs\GenerarRCOF::dispatch($contribuyente, $usuario)->onQueue('documento');
                            \App\Models\Contribuyente::where('id', $contribuyente->id)->update([
                                'contador_boletas' => $contribuyente->contador_boletas + 5,
                            ]);
                        }
                    }
                }
            }
        })->dailyAt('02:00');
        // Se revisan los documentos con estado en proceso
        $schedule->call(function(){
            $documentos = Documento::where('estado', 0)->get();
            Log::info('Se revisara el estado de '.count($documentos).' documentos.');
            foreach($documentos as $doc){
                $contribuyente = Contribuyente::find($doc->ref_contribuyente);
                $usuarios = $contribuyente->users;
                $usuario = null;
                if(count($usuarios) > 0){
                    $usuario = $usuarios[0];
                }
                if($usuario != null){
                    $rut = explode('-', $contribuyente->rut);
                    $firma = FirmaElectronica::temporalPEM($usuario);
                    $token = Autenticacion::getToken($firma);
                    $estado = Sii::request('QueryEstUp', 'getEstUp', [$rut[0], $rut[1], $doc->trackid, $token]);
                    $resp = null;
                    // si el estado se pudo recuperar se muestra estado y glosa
                    if ($estado!==false) {
                        $resp = [
                            'codigo' => (!isset($estado->xpath('/SII:RESPUESTA/SII:RESP_HDR/ESTADO')[0]))?'':(string)$estado->xpath('/SII:RESPUESTA/SII:RESP_HDR/ESTADO')[0],
                            'glosa' => (!isset($estado->xpath('/SII:RESPUESTA/SII:RESP_HDR/GLOSA')[0]))?'':(string)$estado->xpath('/SII:RESPUESTA/SII:RESP_HDR/GLOSA')[0],
                            'aceptados' => (!isset($estado->xpath('/SII:RESPUESTA/SII:RESP_BODY/ACEPTADOS')[0]))?0:(int)$estado->xpath('/SII:RESPUESTA/SII:RESP_BODY/ACEPTADOS')[0],
                            'rechazados' => (!isset($estado->xpath('/SII:RESPUESTA/SII:RESP_BODY/RECHAZADOS')[0]))?0:(int)$estado->xpath('/SII:RESPUESTA/SII:RESP_BODY/RECHAZADOS')[0],
                            'reparos' => (!isset($estado->xpath('/SII:RESPUESTA/SII:RESP_BODY/REPAROS')[0]))?0:(int)$estado->xpath('/SII:RESPUESTA/SII:RESP_BODY/REPAROS')[0],
                        ];
                    }
                    if($resp['aceptados'] == 1){
                        $doc->estado = 1;
                    }
                    if($resp['reparos'] == 1){
                        $doc->estado = 2;
                    }
                    if($resp['rechazados'] == 1){
                        $doc->estado = 3;
                    }
                    $doc->save();
                }
            }
        })->everyThirtyMinutes();
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
