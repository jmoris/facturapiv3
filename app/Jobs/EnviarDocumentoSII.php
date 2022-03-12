<?php

namespace App\Jobs;

use App\Helpers\FirmaElectronica;
use App\Models\Boleta;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SolucionTotal\CoreDTE\Sii\Dte;
use SolucionTotal\CoreDTE\Sii\EnvioDte;

class EnviarDocumentoSII implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $contribuyente;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($contribuyente)
    {
        $this->contribuyente = $contribuyente;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try{
            $path = $this->contribuyente->id;
            $archivos = Storage::disk('tmp')->files($path);
            if(count($archivos) > 0){
                $firma = FirmaElectronica::temporalPEM(auth()->user()->id);

                $ambiente = 0;

                $caratula = [
                    'RutEnvia' => $firma->getID(),
                    'RutReceptor' => '60803000-K',
                    'FchResol' => date('Y-m-d', strtotime(($ambiente==0)?$this->contribuyente->fch_resolucion_prod:$this->contribuyente->fch_resolucion_dev)),
                    'NroResol' => ($ambiente==0)?$this->contribuyente->nro_resolucion_prod:$this->contribuyente->nro_resolucion_dev,
                ];

                $envio = new EnvioDte();

                $documentos = [];
                foreach ($archivos as $filename) {
                    $xml = Storage::disk('tmp')->get($filename);
                    $dte = new Dte($xml);
                    array_push($documentos, ["tipo" => $dte->getTipo(), "folio" => $dte->getFolio()]);
                    $envio->agregar($dte);
                }
                $envio->setFirma($firma);
                $envio->setCaratula($caratula);
                $envio_xml = $envio->generar();

                $trackid = $envio->enviar(null, true);
                if($trackid == 0){
                    throw new Exception("El SII no respondio el envio.");
                }
                foreach ($documentos as $doc) {
                    Boleta::where('folio', $doc['folio'])->where('tipo', $doc['tipo'])->where('ref_contribuyente', $this->contribuyente->id)->update(["trackid" => $trackid]);
                }

                UploadDocumento::dispatch($envio_xml, $trackid, date('Y-m-d'), $this->contribuyente)->onQueue('documento');
                Log::info('Trackid del envio: '. $trackid);
                Storage::disk('tmp')->delete($archivos);
            }
        }catch(Exception $ex){
            Log::error($ex);
            Log::error('Hubo un error al generar el documento de envio.\n'.$ex->getMessage());
        }
    }
}
