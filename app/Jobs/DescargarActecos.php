<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\Acteco;
use Illuminate\Support\Facades\Log;

class DescargarActecos implements ShouldQueue
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
        ini_set('max_execution_time', 300); //300 seconds = 5 minutes
        $html = file_get_contents('http://www.sii.cl/ayudas/ayudas_por_servicios/1956-codigos-1959.html');
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $tables = $dom->getElementsByTagName('table');

        $rows = $tables->item(0)->getElementsByTagName('tr');
        $contador = 0;
        foreach ($rows as $row) {
                $cols = $row->getElementsByTagName('td');
                if($cols->item(0) != null){
                    $codigo = $cols->item(0)->textContent;
                    $descripcion = $cols->item(1)->textContent;
                    $afecto = $cols->item(2)->textContent;
                    if($codigo != 'Código'){
                        echo $codigo.' : '.preg_replace('/\s+/', ' ',$descripcion).'('.(($afecto=='SI')?1:0).')'.PHP_EOL;
                        $acteco = new Acteco();
                        $acteco->id = $codigo;
                        $acteco->descripcion = $descripcion;
                        $acteco->afecto = (($afecto=='SI')?1:0);
                        $acteco->save();
                    }
                }
        }
        echo 'Actecos existentes: ' . $contador;
        Log::info("Actecos existentes: " . $contador);
    }
}
