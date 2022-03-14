<?php

namespace App\Jobs;

use App\Helpers\FirmaElectronica;
use App\Helpers\Formatos;
use App\Helpers\Herramientas;
use App\Models\Boleta;
use App\Models\RCOF;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SolucionTotal\CoreDTE\Sii;
use SolucionTotal\CoreDTE\Sii\ConsumoFolio;

class GenerarRCOF implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $contribuyente;
    private $usuario;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($contribuyente, $usuario)
    {
        $this->contribuyente = $contribuyente;
        $this->usuario = $usuario;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try{
            $ambiente = $this->contribuyente->ambiente;
            Sii::setAmbiente($ambiente);
            $ayer = date("Y-m-d", strtotime( '-1 days' ) );
            $boletas_afectas = Boleta::where('ref_contribuyente', $this->contribuyente->id)->where('tipo', 39)->whereDate('fecha', $ayer)->get();
            $boletas_exentas = Boleta::where('ref_contribuyente', $this->contribuyente->id)->where('tipo', 41)->whereDate('fecha', $ayer)->get();
            // Obtenemos la firma del contribuyente
            $Firma = FirmaElectronica::temporalPEM($this->usuario);
            // crear objeto para consumo de folios
            $ConsumoFolio = new ConsumoFolio();
            $ConsumoFolio->setFirma($Firma);
            $Documentos = [];
            if(count($boletas_afectas) != 0){
                array_push($Documentos, 39);
            }
            if(count($boletas_exentas) != 0){
                array_push($Documentos, 41);
            }
            $montos[39] = 0;
            $montos[41] = 0;
            $montos[61] = 0;
            $ConsumoFolio->setDocumentos($Documentos); // [39, 61] si es sólo afecto, [41, 61] si es sólo exento
            foreach($boletas_afectas as $boleta){
                $valores = Formatos::calcularNetoIVA($boleta->monto_total);
                $resumen =  [
                    'TpoDoc' => 39,
                    'NroDoc' => $boleta->folio,
                    'TasaImp' => 0,
                    'FchDoc' => date('Y-m-d', strtotime($boleta->fecha)),
                    'CdgSIISucur' => false,
                    'RUTDoc' => ($boleta->cliente != null)?$boleta->cliente->rut:'66666666-6',
                    'RznSoc' => ($boleta->cliente != null)?$boleta->cliente->razon_social:false,
                    'MntExe' => $boleta->monto_exento,
                    'MntNeto' => $valores[0],
                    'MntIVA' => $valores[1],
                    'MntTotal' => $boleta->monto_total,
                ];
                $montos[39]+=$valores[0];
                $ConsumoFolio->agregar($resumen);
            }
            foreach($boletas_exentas as $boleta){
                $valores = Formatos::calcularNetoIVA($boleta->monto_total);
                $resumen =  [
                    'TpoDoc' => 41,
                    'NroDoc' => $boleta->folio,
                    'TasaImp' => 0,
                    'FchDoc' => date('Y-m-d', strtotime($boleta->fecha)),
                    'CdgSIISucur' => false,
                    'RUTDoc' => ($boleta->cliente != null)?$boleta->cliente->rut:'66666666-6',
                    'RznSoc' => ($boleta->cliente != null)?$boleta->cliente->razon_social:false,
                    'MntExe' => $boleta->monto_exento,
                    'MntNeto' => $valores[0],
                    'MntIVA' => $valores[1],
                    'MntTotal' => $boleta->monto_total,
                ];
                $montos[41]+=$valores[0];
                $ConsumoFolio->agregar($resumen);
            }
            $ConsumoFolio->setCaratula([
                'RutEmisor' => str_replace(".", "", $this->contribuyente->rut),
                'FchResol' => date('Y-m-d', strtotime(($ambiente==0)?$this->contribuyente->fch_resolucion_prod:$this->contribuyente->fch_resolucion_dev)),
                'NroResol' => ($ambiente==0)?$this->contribuyente->nro_resolucion_prod:$this->contribuyente->nro_resolucion_dev,
            ]);
            // generar, validar schema y mostrar XML
            $xml = $ConsumoFolio->generar();
            if ($ConsumoFolio->schemaValidate()) {
                //echo $ConsumoFolio->generar();
                $track_id = $ConsumoFolio->enviar();
                if($track_id == 0){
                    throw new Exception('El SII no ha respondido.');
                }

                Log::info("Consumo folio ctr " . $this->contribuyente->id. " trackid: ".$track_id);

                $fecha = date('Y-m-d');

                $fileNameXML = 'RCOF'.date('d-m-Y', strtotime('-1 days')).'.xml';
                $ambiente = $this->contribuyente->ambiente;
                $str_ambiente = ($ambiente)?'certificacion':'produccion';
                $path = $str_ambiente.'/contribuyentes/'.$this->contribuyente->id.'/'.date('Y', strtotime($fecha)).'/'.date('m', strtotime($fecha)).'/RCOF/';
                Storage::put($path.$fileNameXML, $xml);

                $rcof = new RCOF();
                $rcof->fecha = $ayer;
                $rcof->trackid = $track_id;
                $rcof->ref_contribuyente = $this->contribuyente->id;
                $rcof->monto_39 = $montos[39];
                $rcof->monto_41 = $montos[41];
                $rcof->save();
                Log::info("[RCOF] Consumo de folios contribuyente " . $this->contribuyente->id. " enviado.");
            }else{
                $errores = "";
                \SolucionTotal\CoreDTE\Log::clearError();
                foreach (\SolucionTotal\CoreDTE\Log::readAll() as $error){
                    $errores .= $error.'\n';
                }
                echo $errores;
                Log::info("[RCOF] Consumo de folios contribuyente " . $this->contribuyente->id. " con error.");
                Log::error($errores);
            }
        }catch(\Exception $ex){
            Log::error($ex);
            return $ex->getMessage();
        }
    }
}
