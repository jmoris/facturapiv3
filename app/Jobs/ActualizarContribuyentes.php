<?php

namespace App\Jobs;

use App\Helpers\FirmaElectronica;
use App\Models\InfoContribuyente;
use App\Models\User;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use SolucionTotal\CoreDTE\Sii;

class ActualizarContribuyentes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $user = User::find(1);
        $firma = FirmaElectronica::temporalPEM($user);
        $cookies = \SolucionTotal\CoreDTE\Sii\Autenticacion::requestCookies($firma);
        Sii::setAmbiente(Sii::PRODUCCION);
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 900); // 15 minutos de timeout en esta funcion
        $response = Sii::getListadoContribuyentes($cookies);
        ini_set('memory_limit', '1024M');
        $lines = explode("\n", $response);
        $n_lines = count($lines);
        $c = 0;
        try{
            for ($i=1; $i<$n_lines; $i++) {
                $line = mb_convert_encoding(trim(preg_replace('/\s+/', ' ', $lines[$i])), "ISO-8859-1", "UTF-8");
                $rows = explode(';', $line);
                if(count($rows) >= 4){
                    if(InfoContribuyente::find($rows[0])!=null){
                        $ic = InfoContribuyente::find($rows[0]);
                        $ic->rut = $rows[0];
                        $ic->razon_social = $rows[1];
                        $ic->nro_resolucion = intval($rows[2]);
                        $ic->fch_resolucion = date('Y-m-d', strtotime(
                            $rows[3]
                        ));
                        $ic->correo_dte = $rows[4];
                        $ic->save();
                    }else{
                        $ic = new InfoContribuyente();
                        $ic->rut = $rows[0];
                        $ic->razon_social = $rows[1];
                        $ic->nro_resolucion = intval($rows[2]);
                        $ic->fch_resolucion = date('Y-m-d', strtotime(
                            $rows[3]
                        ));
                        $ic->correo_dte = $rows[4];
                        $ic->save();
                    }

                    $c++;
                    //array_push($data, $ctr_data);
                }else{
                    continue;
                }
            }
            return response()->json([
                'status' => 'ok',
                'contribuyentes' => $c
            ]);
        }catch(Exception $ex){
            Log::info("Linea excepcion: ".$i.PHP_EOL);
            Log::error($ex);
        }
    }
}
