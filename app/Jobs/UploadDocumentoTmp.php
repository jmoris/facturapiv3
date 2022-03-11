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

class UploadDocumentoTmp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $xml;
    private $tipo;
    private $folio;
    private $fecha;
    private $contribuyente;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($xml, $tipo, $folio, $fecha, $contribuyente)
    {
        $this->xml = $xml;
        $this->tipo = $tipo;
        $this->folio = $folio;
        $this->fecha = $fecha;
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
            /*
            /produccion/contribuyentes/1/2021/12/RCOF
            /produccion/contribuyentes/1/2021/12/Documentos
            /produccion/contribuyentes/1/2021/12/AEC
            */
            $ambiente = env('AMBIENTE', 1);
            $str_ambiente = ($ambiente==0)?'produccion':'certificacion';
            $path = $str_ambiente.'/contribuyentes/'.$this->contribuyente->id.'/'.date('Y', strtotime($this->fecha)).'/'.date('m', strtotime($this->fecha)).'/documentos/';
            Storage::put($path.'DTET'.$this->tipo.'F'.$this->folio.'.xml',  $this->xml);
        }catch(Exception $ex){
            Log::error('Hubo un error guardando el archivo temporal del DTE.\n'.$ex->getMessage());
        }
    }
}
