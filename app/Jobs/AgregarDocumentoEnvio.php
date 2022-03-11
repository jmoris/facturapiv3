<?php

namespace App\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SolucionTotal\CoreDTE\Sii\Dte;

class AgregarDocumentoEnvio implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $xml;
    private $folio;
    private $tipo;
    private $contribuyente;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($xml, $folio, $tipo, $contribuyente)
    {
        $this->xml = $xml;
        $this->folio = $folio;
        $this->tipo = $tipo;
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
            Storage::disk('tmp')->put($this->contribuyente->id.'/DTET'.$this->tipo.'F'.$this->folio.'.xml',  $this->xml);
        }catch(Exception $ex){
            Log::error('Hubo un error guardando el archivo temporal del DTE.\n'.$ex->getMessage());
        }
    }
}
