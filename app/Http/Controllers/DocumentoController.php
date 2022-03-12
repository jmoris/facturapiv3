<?php

namespace App\Http\Controllers;

use App\Helpers\FirmaElectronica as HelpersFirmaElectronica;
use App\Helpers\Formatos;
use App\Helpers\Herramientas;
use App\Jobs\AgregarDocumentoEnvio;
use App\Jobs\EnviarDocumentoSII;
use App\Jobs\UploadDocumentoTmp;
use App\Models\Acteco;
use App\Models\Boleta;
use App\Models\CAF;
use App\Models\Contribuyente;
use App\Models\DireccionContribuyente;
use App\Models\Documento;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use SolucionTotal\CoreDTE\FirmaElectronica;
use SolucionTotal\CoreDTE\Modelos\Detalle;
use SolucionTotal\CoreDTE\Modelos\Factura;
use SolucionTotal\CoreDTE\Modelos\Receptor;
use SolucionTotal\CoreDTE\Sii;
use SolucionTotal\CoreDTE\Sii\ConsumoFolio;
use SolucionTotal\CoreDTE\Sii\EnvioDte;

class DocumentoController extends Controller
{

    public function getBoletas(Request $request){
        try{
            $validador = Validator::make($request->all(), [
                'contribuyente' => 'required|exists:contribuyentes,rut',
                'folio' => '',
                'ano' => '',
                'mes' => '',
                'dia' => ''
            ]);

            if ($validador->fails()) {
                return response()->json([
                    'status' => 500,
                    'msg' => 'No se pudieron validar los datos',
                    'error' => $validador->errors()
                ]);
            }
            $boletas = null;
            $contribuyente = Contribuyente::where('rut', $request->contribuyente)->first();
            if(isset($request->folio)){
                $boletas = Boleta::where('ref_contribuyente', $contribuyente->id)->where('folio', $request->folio)->first();
                if($boletas == null)
                    return response()->json(['status' => 400, 'error' => 'No existe una boleta con este folio.']);
            }else{
                $boletas = Boleta::where('ref_contribuyente', $contribuyente->id);
                if(isset($request->ano)){
                    $boletas = $boletas->whereYear('fecha', $request->ano );
                }
                if(isset($request->mes)){
                    $boletas = $boletas->whereMonth('fecha', $request->mes );
                }
                if(isset($request->dia)){
                    $boletas = $boletas->whereDay('fecha', $request->dia );
                }
                $boletas = $boletas->paginate(15);
            }
            return $boletas;
        }catch(Exception $ex){
            return response()->json([
                'status' => 500,
                'error' => $ex->getMessage()
            ]);
        }
    }

    public function storeBoleta(Request $request){
        $receptor = null;
        $folio = null;
        $numero = null;
        try{
            // Se valida la información recibida
            $validador = Validator::make($request->all(), [
                'contribuyente' => 'required|exists:contribuyentes,rut',
                'acteco' => 'required',
                'tipo' => 'required|in:39,41',
                'fecha' => 'nullable|date',
                'receptor' => 'nullable',
                'detalles' => 'nullable|array',
                'referencias' => 'nullable|array',
            ]);

            if ($validador->fails()) {
                return response()->json([
                    'status' => 500,
                    'msg' => 'No se pudieron validar los datos',
                    'error' => $validador->errors()
                ]);
            }
            // Se busca el contribuyente y se fija el ambiente en el que se trabajara
            $contribuyente = Contribuyente::where('rut', $request->contribuyente)->first();
            $ambiente = $contribuyente->ambiente;
            \SolucionTotal\CoreDTE\Sii::setAmbiente($ambiente);
            // Se obtiene la firma electronica
            $firma = HelpersFirmaElectronica::temporalPEM();
            // Se verifica la existencia de folios
            $caf = null;
            if($ambiente == 0){
                $caf = CAF::where('tipo', $request->tipo)->where('ref_contribuyente', $contribuyente->id)->first();
            }
            // En caso de no existir folios, se vuelven a pedir y en caso de estos *estar invalidos, se anulan y vuelven a pedir*
            if($caf == null){
                $cookies = \SolucionTotal\CoreDTE\Sii\Autenticacion::requestCookies($firma);
                //Timbraje automatico
                $folio = new \SolucionTotal\CoreDTE\Sii\Folios();
                $numero = $folio->timbrar($firma, $request->contribuyente, $request->tipo, ($ambiente==1)?1:30, $cookies);
                if($ambiente == 0){
                    $caf = new CAF();
                    $caf->tipo = $request->tipo;
                    $caf->ref_contribuyente = $contribuyente->id;
                    $caf->folio = $numero;
                    $caf->xml = $folio->saveXML();
                    $caf->save();
                }
            }else{
                $folio = new \SolucionTotal\CoreDTE\Sii\Folios(stripslashes($caf->xml));
                $numero = $caf->folio;
                if($folio->getHasta() < $caf->folio){
                    $caf->delete();
                    $cookies = \SolucionTotal\CoreDTE\Sii\Autenticacion::requestCookies($firma);
                    $folio = new \SolucionTotal\CoreDTE\Sii\Folios();
                    $numero = $folio->timbrar($firma, $request->contribuyente, $request->tipo, ($ambiente==1)?1:30, $cookies);
                    if($ambiente == 0){
                        $caf = new CAF();
                        $caf->tipo = $request->tipo;
                        $caf->ref_contribuyente = $contribuyente->id;
                        $caf->folio = $numero;
                        $caf->xml = $folio->saveXML();
                        $caf->save();
                    }
                }
            }
            if($caf != null){
                // Se aumenta el folio en 1 para mantener la correlacion de documentos
                $caf->folio = $caf->folio + 1 ;
                $caf->save();
            }
            $contribuyente->contador_boletas =  $contribuyente->contador_boletas + 1;
            $contribuyente->save();
            // Se busca la información para crear el documento, se busca el codigo de actividad economica y su domicilio
            $acteco = $contribuyente->actecos()->where('ref_acteco', $request->acteco)->first();
            if($acteco == null){
                throw new Exception("No se encuentra la actividad economica.");
            }
            $domicilio = DireccionContribuyente::where('ref_contribuyente', $contribuyente->id)->where('tipo', 'DOMICILIO')->first();
            // Asignacion emisor y receptor
            $emisor = new \SolucionTotal\CoreDTE\Modelos\Emisor($contribuyente->rut, $contribuyente->razon_social, Formatos::sanitizarString($acteco->descripcion), $request->acteco, $domicilio->direccion, $domicilio->comuna->nombre);
            if($request->receptor == null){
                $receptor = new Receptor('66666666-6', null, null, null, null);
            }else{
                $receptor = new Receptor($request->receptor['rut'], $request->receptor['razon_social'], 'SIN DEFINIR', $request->receptor['direccion'], $request->receptor['comuna']);
            }
            $fecha = null;
            if($request->fecha == null){
                $fecha = date('Y-m-d H:i');
            }else{
                $fecha = $request->fecha;
            }
            $boleta = new \SolucionTotal\CoreDTE\Modelos\Boleta(date('Y-m-d', strtotime($fecha)), $emisor, $receptor);
            $boleta->setFolio($numero);
            $detalles = $request->detalles;
            if($detalles == null){
                $detalles = [];
                array_push($detalles, [
                    'nombre' => 'Sin detalle',
                    'unidad' => 'Und',
                    'cantidad' => 1,
                    'precio' => 990
                ]);
            }
            foreach($detalles as $detalle){
                $det = new Detalle($detalle['nombre'], $detalle['unidad'], $detalle['cantidad'], $detalle['precio']);
                if(isset($detalle['descuento'])){
                    $det->setDescuento($detalle['descuento']);
                }
                $boleta->setDetalle($det);
            }
            $dte = $boleta->getBoleta($firma, $folio);
            AgregarDocumentoEnvio::dispatch($dte->saveXML(), $numero, $request->tipo, $contribuyente)->onQueue('documento');
            EnviarDocumentoSII::dispatch($contribuyente, auth()->user())->onQueue('envios')->delay(now()->addMinutes(1));
            $montos = $dte->getTotales();
            if($ambiente==0){
                $doc = new Boleta();
                $doc->folio = $numero;
                $doc->tipo = $request->tipo;
                $doc->fecha = $fecha;
                $doc->ref_cliente = ($request->receptor != null)?$request->receptor['rut']: null;
                $doc->ref_contribuyente = $contribuyente->id;
                // Se obtienen los montos del documento y estos se guardan en la base de datos
                $doc->monto_exento = $montos['MntExe'];
                $doc->monto_neto = $montos['MntNeto'];
                $doc->monto_iva = $montos['IVA'];
                $doc->monto_total = $montos['MntTotal'];
                $doc->save();
            }
            $pdf417 = new \BigFish\PDF417\PDF417();
            $pdf417->setColumns(12);
            $pdf417->setSecurityLevel(5);
            $data = $pdf417->encode($dte->getTED());

            $renderer = new \BigFish\PDF417\Renderers\ImageRenderer([
                'format' => 'data-url',
                'ratio' => 2,
                'scale' => 3,
                'padding' => 0
            ]);
            $generado = str_replace('data:image/png;base64,', '', $renderer->render($data));
            return [
                'PDF' => $generado,
                'folio' => $numero,
                'fecha' => date('d/m/Y H:i', strtotime($fecha)),
                'sucsii' => Sii::getDireccionRegional($domicilio->comuna->nombre),
                'totales' => [
                    'exento' => $montos['MntExe'],
                    'neto' => $montos['MntNeto'],
                    'iva' => $montos['IVA'],
                    'total' => $montos['MntTotal']
                ]];
        }catch(\Exception $ex){
            Log::error($ex);
            return response()->json([
                'status' => 500,
                'error' => $ex->getMessage()
            ]);
        }
    }

    public function reimprimirBoleta(Request $request){
        try{
            $validador = Validator::make($request->all(), [
                'contribuyente' => 'required|exists:contribuyentes,rut',
                'tipo' => 'required',
                'folio' => 'nullable'
            ]);

            if ($validador->fails()) {
                return response()->json([
                    'status' => 500,
                    'msg' => 'No se pudieron validar los datos',
                    'error' => $validador->errors()
                ]);
            }
            $contribuyente = Contribuyente::where('rut', $request->contribuyente)->first();
            /*$user = $request->user();
            if(!$user->contribuyentes->contains($contribuyente->id)){
                return response()->json([
                    'status' => 500,
                    'error' => 'El usuario conectado no tiene acceso a este contribuyente',
                ]);
            }*/
            $boleta = null;
            if($request->folio == null){
                $boleta = Boleta::where('tipo', $request->tipo)->where('ref_contribuyente', $contribuyente->id)->orderBy('folio', 'desc')->first();
            }else{
                $boleta = Boleta::where('tipo', $request->tipo)->where('ref_contribuyente', $contribuyente->id)->where('folio', $request->folio)->first();
            }
            $domicilio = DireccionContribuyente::where('ref_contribuyente', $contribuyente->id)->where('tipo', 'DOMICILIO')->first();

            $ambiente = 0;
            $str_ambiente = ($ambiente)?'certificacion':'produccion';
            $path = $str_ambiente.'/contribuyentes/'.$contribuyente->id.'/'.date('Y', strtotime($boleta->fecha)).'/'.date('m', strtotime($boleta->fecha)).'/documentos/';
            $xml = null;
            if(Storage::exists($path.'DTE'.$boleta->trackid.'.xml')) {
                $xml = Storage::get($path.'DTE'.$boleta->trackid.'.xml');
            }else{
                $xml = Storage::disk('tmp')->get($contribuyente->id.'/DTET'.$boleta->tipo.'F'.$boleta->folio.'.xml');
            }
            $envio = new EnvioDte();
            $envio->loadXML($xml);
            $docs = $envio->getDocumentos();
            foreach($docs as $doc){
                if($doc->getFolio() == $boleta->folio && $doc->getTipo() == $request->tipo){
                    $pdf417 = new \BigFish\PDF417\PDF417();
                    $pdf417->setColumns(12);
                    $pdf417->setSecurityLevel(5);
                    $data = $pdf417->encode($doc->getTED());

                    $renderer = new \BigFish\PDF417\Renderers\ImageRenderer([
                        'format' => 'data-url',
                        'ratio' => 2,
                        'scale' => 3,
                        'padding' => 0
                    ]);
                    $generado = str_replace('data:image/png;base64,', '', $renderer->render($data));
                    return ['PDF' => $generado,  'folio' => $boleta->folio, 'fecha' => date('d/m/Y H:i', strtotime($boleta->fecha)), 'sucsii' => Sii::getDireccionRegional($domicilio->comuna->nombre), 'total' => $boleta->monto_total];
                }
            }
            return response()->json([
                'status' => 500,
                'error' => 'Documento no encontrado o inexistente'
            ]);
        }catch(Exception $ex){
            Log::error($ex);
            return response()->json([
                'status' => 500,
                'error' => $ex->getMessage()
            ]);
        }
    }

    public function getReporteFolios(Request $request){
        try{
            $validador = Validator::make($request->all(), [
                'contribuyente' => 'required|exists:contribuyentes,rut',
            ]);

            if ($validador->fails()) {
                return response()->json([
                    'status' => 500,
                    'msg' => 'No se pudieron validar los datos',
                    'error' => $validador->errors()
                ]);
            }
            $contribuyente = Contribuyente::where('rut', $request->contribuyente)->first();
            $ambiente = env('AMBIENTE', 1);
            Sii::setAmbiente($ambiente);
            $ayer = date("Y-m-d", strtotime( '-1 days' ) );
            $boletas_afectas = Boleta::where('ref_contribuyente', $contribuyente->id)->where('tipo', 39)->whereDate('fecha', $ayer)->get();
            $boletas_exentas = Boleta::where('ref_contribuyente', $contribuyente->id)->where('tipo', 41)->whereDate('fecha', $ayer)->get();
            // crear objeto para consumo de folios
            $ConsumoFolio = new ConsumoFolio();
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
                'RutEmisor' => str_replace(".", "", $contribuyente->rut),
                'FchResol' => date('Y-m-d', strtotime(($ambiente==0)?$contribuyente->fch_resolucion_prod:$contribuyente->fch_resolucion_dev)),
                'NroResol' => ($ambiente==0)?$contribuyente->nro_resolucion_prod:$this->contribuyente->nro_resolucion_dev,
            ]);
            // generar, validar schema y mostrar XML
            $xml = $ConsumoFolio->getResumen();
            $xml['FechaConsulta'] = date('d/m/Y', strtotime($ayer));
            return $xml;
        }catch(Exception $ex){
            Log::error($ex);
            return response()->json([
                'status' => 500,
                'error' => $ex->getMessage()
            ]);
        }
    }

    public function consultaEnvioBoleta(Request $request){
        try{
            $validador = Validator::make($request->all(), [
                'contribuyente' => 'required|exists:contribuyentes,rut',
                'trackid' => 'required',
            ]);

            if ($validador->fails()) {
                return response()->json([
                    'status' => 500,
                    'msg' => 'No se pudieron validar los datos',
                    'error' => $validador->errors()
                ]);
            }
            $ambiente = env('AMBIENTE', 1);
            \SolucionTotal\CoreDTE\Sii::setAmbiente($ambiente);
            $firma = HelpersFirmaElectronica::temporalPEM();
            $estado = \SolucionTotal\CoreDTE\Sii::estadoBoleta($firma, $request->contribuyente, $request->trackid);
            return $estado;
        }catch(Exception $ex){
            return response()->json([
                'status' => 500,
                'error' => $ex->getMessage()
            ]);
        }
    }

    public function generarPDF(Request $request){
        try{

            $validador = Validator::make($request->all(), [
                'contribuyente' => 'required|exists:contribuyentes,rut',
                'tipo' => 'required',
                'folio' => 'nullable',
                'logo' => 'required',
                'poslogo' => 'required',
                'papel' => 'required',
                'visor' => 'required'
            ]);

            if ($validador->fails()) {
                return response()->json([
                    'status' => 500,
                    'msg' => 'No se pudieron validar los datos',
                    'error' => $validador->errors()
                ]);
            }
            $contribuyente = Contribuyente::where('rut', $request->contribuyente)->first();

            $documento = null;
            if($request->folio == null){
                $documento = Documento::where('tipo', $request->tipo)->where('ref_contribuyente', $contribuyente->id)->orderBy('folio', 'desc')->first();
            }else{
                $documento = Documento::where('tipo', $request->tipo)->where('ref_contribuyente', $contribuyente->id)->where('folio', $request->folio)->first();
            }

            if($documento == null){
                return response()->json([
                    'status' => 500,
                    'msg' => 'Documento no encontrado'
                ]);
            }

            $domicilio = DireccionContribuyente::where('ref_contribuyente', $contribuyente->id)->where('tipo', 'DOMICILIO')->first();

            $ambiente = 0;
            $str_ambiente = ($ambiente)?'certificacion':'produccion';
            $path = $str_ambiente.'/contribuyentes/'.$contribuyente->id.'/'.date('Y', strtotime($documento->fecha)).'/'.date('m', strtotime($documento->fecha)).'/documentos/';
            $xml = null;
            if(Storage::exists($path.'DTE'.$documento->trackid.'.xml')) {
                $xml = Storage::get($path.'DTE'.$documento->trackid.'.xml');
            }else{
                $xml = Storage::disk('tmp')->get($contribuyente->id.'/DTET'.$documento->tipo.'F'.$documento->folio.'.xml');
            }
            $EnvioDTEg = new \SolucionTotal\CoreDTE\Sii\EnvioDte();
            $EnvioDTEg->loadXML($xml);
            $caratula = $EnvioDTEg->getCaratula();
            $dte = $EnvioDTEg->getDocumentos()[0];
            $datos = $dte->getDatos();
            if ($request->tipo == 0) {
                $datos['Encabezado']['IdDoc']['TipoDTE'] = 0;
            }

            $pdf = new \SolucionTotal\CorePDF\PDF($datos, $request->papel, $request->logo, $request->poslogo, $dte->getTED());
            //$pdf->setLeyendaImpresion('Sistema de facturación por SoluciónTotal');
            //$pdf->setMarcaAgua($config->path_logo);
            //$pdf->setTelefono($cbt->telefono);
            //$pdf->setMail($cbt->mail);
            //$pdf->setWeb($cbt->web);
            $pdf->setResolucion(date('Y', strtotime($caratula['FchResol'])), $caratula['NroResol']);
            /*if($doc->glosa != '')
                $pdf->setGlosa($doc->glosa);*/
            $pdf->construir();
            if($request->visor==1){
                $pdf->generar(1); // 1 genera para vista web*/
            }else{
                return ['PDF' => chunk_split(base64_encode($pdf->generar(3)))];
            }
        }catch(Exception $ex){
            return $ex;
            return response()->json([
                'status' => 500,
                'msg' => 'Hubo un error al intentar generar el PDF'
            ]);
        }
    }

    public function getDocumento(Request $request){
        try{
            $validador = Validator::make($request->all(), [
                'contribuyente' => 'required|exists:contribuyentes,rut',
                'tipo' => 'required',
                'folio' => 'nullable'
            ]);

            if ($validador->fails()) {
                return response()->json([
                    'status' => 500,
                    'msg' => 'No se pudieron validar los datos',
                    'error' => $validador->errors()
                ]);
            }
            $contribuyente = Contribuyente::where('rut', $request->contribuyente)->first();
            $documento = null;
            if($request->folio == null){
                $documento = Documento::where('tipo', $request->tipo)->where('ref_contribuyente', $contribuyente->id)->orderBy('folio', 'desc')->first();
            }else{
                $documento = Documento::where('tipo', $request->tipo)->where('ref_contribuyente', $contribuyente->id)->where('folio', $request->folio)->first();
            }
            $domicilio = DireccionContribuyente::where('ref_contribuyente', $contribuyente->id)->where('tipo', 'DOMICILIO')->first();

            $ambiente = 0;
            $str_ambiente = ($ambiente)?'certificacion':'produccion';
            $path = $str_ambiente.'/contribuyentes/'.$contribuyente->id.'/'.date('Y', strtotime($documento->fecha)).'/'.date('m', strtotime($documento->fecha)).'/documentos/';
            $xml = null;
            if(Storage::exists($path.'DTE'.$documento->trackid.'.xml')) {
                $xml = Storage::get($path.'DTE'.$documento->trackid.'.xml');
            }else{
                $xml = Storage::disk('tmp')->get($contribuyente->id.'/DTET'.$documento->tipo.'F'.$documento->folio.'.xml');
            }
            $envio = new EnvioDte();
            $envio->loadXML($xml);
            $docs = $envio->getDocumentos();
            foreach($docs as $doc){
                if($doc->getFolio() == $documento->folio && $doc->getTipo() == $request->tipo){
                    return response()->json($doc->getDatos());
                }
            }
            return response()->json([
                'status' => 500,
                'error' => 'Documento no encontrado o inexistente'
            ]);
        }catch(Exception $ex){
            Log::error($ex);
            return response()->json([
                'status' => 500,
                'error' => $ex->getMessage()
            ]);
        }
    }

    public function reimprimirDocumento(Request $request){
        try{
            $validador = Validator::make($request->all(), [
                'contribuyente' => 'required|exists:contribuyentes,rut',
                'tipo' => 'required',
                'folio' => 'nullable'
            ]);

            if ($validador->fails()) {
                return response()->json([
                    'status' => 500,
                    'msg' => 'No se pudieron validar los datos',
                    'error' => $validador->errors()
                ]);
            }
            $contribuyente = Contribuyente::where('rut', $request->contribuyente)->first();
            /*$user = $request->user();
            if(!$user->contribuyentes->contains($contribuyente->id)){
                return response()->json([
                    'status' => 500,
                    'error' => 'El usuario conectado no tiene acceso a este contribuyente',
                ]);
            }*/
            $documento = null;
            if($request->folio == null){
                $documento = Documento::where('tipo', $request->tipo)->where('ref_contribuyente', $contribuyente->id)->orderBy('folio', 'desc')->first();
            }else{
                $documento = Documento::where('tipo', $request->tipo)->where('ref_contribuyente', $contribuyente->id)->where('folio', $request->folio)->first();
            }
            $domicilio = DireccionContribuyente::where('ref_contribuyente', $contribuyente->id)->where('tipo', 'DOMICILIO')->first();

            $ambiente = 0;
            $str_ambiente = ($ambiente)?'certificacion':'produccion';
            $path = $str_ambiente.'/contribuyentes/'.$contribuyente->id.'/'.date('Y', strtotime($documento->fecha)).'/'.date('m', strtotime($documento->fecha)).'/documentos/';
            $xml = null;
            if(Storage::exists($path.'DTE'.$documento->trackid.'.xml')) {
                $xml = Storage::get($path.'DTE'.$documento->trackid.'.xml');
            }else{
                $xml = Storage::disk('tmp')->get($contribuyente->id.'/DTET'.$documento->tipo.'F'.$documento->folio.'.xml');
            }
            $envio = new EnvioDte();
            $envio->loadXML($xml);
            $docs = $envio->getDocumentos();
            foreach($docs as $doc){
                if($doc->getFolio() == $documento->folio && $doc->getTipo() == $request->tipo){
                    $pdf417 = new \BigFish\PDF417\PDF417();
                    $pdf417->setColumns(12);
                    $pdf417->setSecurityLevel(5);
                    $data = $pdf417->encode($doc->getTED());

                    $renderer = new \BigFish\PDF417\Renderers\ImageRenderer([
                        'format' => 'data-url',
                        'ratio' => 2,
                        'scale' => 3,
                        'padding' => 0
                    ]);
                    $generado = str_replace('data:image/png;base64,', '', $renderer->render($data));
                    return ['PDF' => $generado,  'folio' => $documento->folio, 'fecha' => date('d/m/Y H:i', strtotime($documento->fecha)), 'sucsii' => Sii::getDireccionRegional($domicilio->comuna->nombre), 'total' => $documento->monto_total];
                }
            }
            return response()->json([
                'status' => 500,
                'error' => 'Documento no encontrado o inexistente'
            ]);
        }catch(Exception $ex){
            Log::error($ex);
            return response()->json([
                'status' => 500,
                'error' => $ex->getMessage()
            ]);
        }
    }

    public function storeDocumento(Request $request){
        $receptor = null;
        $folio = null;
        $numero = null;
        $documento = null;
        $contribuyente = null;
        try{
            // Se valida la información recibida
            $validador = Validator::make($request->all(), [
                'contribuyente' => 'required|exists:contribuyentes,rut',
                'acteco' => 'required',
                'tipo' => ['required', Rule::in(['33', '34', '52', '56', '61']),],
                'fecha' => 'nullable|date',
                'receptor' => 'nullable',
                'sucursal' => 'nullable',
                'forma_pago' => 'nullable',
                'detalles' => 'nullable|array',
                'referencias' => 'nullable|array',
            ]);

            if ($validador->fails()) {
                return response()->json([
                    'status' => 500,
                    'msg' => 'No se pudieron validar los datos',
                    'error' => $validador->errors()
                ]);
            }
            $ambiente = env('AMBIENTE', 0);
            // Se busca el contribuyente y se fija el ambiente en el que se trabajara
            $contribuyente = Contribuyente::where('rut', $request->contribuyente)->first();
            \SolucionTotal\CoreDTE\Sii::setAmbiente($ambiente);
            // Se obtiene la firma electronica
            $firma = HelpersFirmaElectronica::temporalPEM();
            // Se verifica la existencia de folios
            $caf = CAF::where('tipo', $request->tipo)->where('ref_contribuyente', $contribuyente->id)->first();
            // En caso de no existir folios, se vuelven a pedir y en caso de estos *estar invalidos, se anulan y vuelven a pedir*
            if($caf == null){
                $cookies = \SolucionTotal\CoreDTE\Sii\Autenticacion::requestCookies($firma);
                //Timbraje automatico
                $folio = new \SolucionTotal\CoreDTE\Sii\Folios();
                $numero = $folio->timbrar($firma, $request->contribuyente, $request->tipo, ($ambiente==1)?1:30, $cookies);
                if($numero==null)
                    throw new Exception("No se pudo obtener folios");
                if($ambiente==0){
                    $caf = new CAF();
                    $caf->tipo = $request->tipo;
                    $caf->ref_contribuyente = $contribuyente->id;
                    $caf->folio = $numero;
                    $caf->xml = $folio->saveXML();
                    $caf->save();
                }
            }else{
                $folio = new \SolucionTotal\CoreDTE\Sii\Folios(stripslashes($caf->xml));
                $numero = $caf->folio;
                if($folio->getHasta() < $caf->folio||!$folio->esValido()){
                    $caf->delete();
                    $cookies = \SolucionTotal\CoreDTE\Sii\Autenticacion::requestCookies($firma);
                    $folio = new \SolucionTotal\CoreDTE\Sii\Folios();
                    $numero = $folio->timbrar($firma, $request->contribuyente, $request->tipo, ($ambiente==1)?1:30, $cookies);
                    if($numero==null)
                        throw new Exception("No se pudo obtener folios");
                    if($ambiente == 0){
                        $caf = new CAF();
                        $caf->tipo = $request->tipo;
                        $caf->ref_contribuyente = $contribuyente->id;
                        $caf->folio = $numero;
                        $caf->xml = $folio->saveXML();
                        $caf->save();
                    }
                }
            }
            // Se aumenta el folio en 1 para mantener la correlacion de documentos
            if($ambiente==0){
                $caf->folio = $caf->folio + 1 ;
                $caf->save();
            }
            $contribuyente->contador_documentos =  $contribuyente->contador_documentos + 1;
            $contribuyente->save();
            // Se busca la información para crear el documento, se busca el codigo de actividad economica y su domicilio
            $acteco = Acteco::find($request->acteco);
            $domicilio = null;
            if($request->sucursal != null){
                $domicilio = DireccionContribuyente::where('ref_contribuyente', $contribuyente->id)->where('codigo', $request->sucursal)->first();
                if($domicilio == null){
                    return new Exception("La sucursal con este código no existe.");
                }
            }else{
                $domicilio = DireccionContribuyente::where('ref_contribuyente', $contribuyente->id)->where('tipo', 'DOMICILIO')->first();
            }
            // Asignacion emisor y receptor
            $emisor = new \SolucionTotal\CoreDTE\Modelos\Emisor($contribuyente->rut, $contribuyente->razon_social, utf8_encode($acteco->descripcion), $request->acteco, $domicilio->direccion, $domicilio->comuna->nombre);
            if($request->receptor == null){
                $receptor = new Receptor('66666666-6', null, null, null, null);
            }else{
                $receptor = new Receptor($request->receptor['rut'], $request->receptor['razon_social'], $request->receptor['giro'], $request->receptor['direccion'], $request->receptor['comuna']);
            }
            $fecha = null;
            if($request->fecha == null){
                $fecha = date('Y-m-d H:i');
            }else{
                $fecha = $request->fecha;
            }
            switch($request->tipo){
                case 33:
                    $documento = new \SolucionTotal\CoreDTE\Modelos\Factura(date('Y-m-d', strtotime($fecha)), $emisor, $receptor);
                    $documento->setFormaPago(Factura::CREDITO);
                    break;
                case 34:
                    $documento = new \SolucionTotal\CoreDTE\Modelos\Factura(date('Y-m-d', strtotime($fecha)), $emisor, $receptor);
                    $documento->setExenta(true);
                    break;
                case 52:
                    $documento = new \SolucionTotal\CoreDTE\Modelos\GuiaDespacho(date('Y-m-d', strtotime($fecha)), $emisor, $receptor);
                    break;
                case 56:
                    $documento = new \SolucionTotal\CoreDTE\Modelos\NotaDebito(date('Y-m-d', strtotime($fecha)), $emisor, $receptor);
                    break;
                case 61:
                    $documento = new \SolucionTotal\CoreDTE\Modelos\NotaCredito(date('Y-m-d', strtotime($fecha)), $emisor, $receptor);
                    break;
            }

            if($request->forma_pago != null){
                $documento->setFormaPago($request->forma_pago);
            }

            $documento->setFolio($numero);

            foreach($request->referencias as $referencia){
                $documento->setReferencia($referencia['tipo'], $referencia['folio'], $referencia['fecha'], $referencia['razon'], $referencia['codigo']);
            }
            foreach($request->detalles as $detalle){
                $det = new Detalle($detalle['nombre'], $detalle['unidad'], $detalle['precio'], $detalle['cantidad']);

                if(isset($detalle['descuento'])){
                    $det->setDescuento($detalle['descuento']);
                }
                if(isset($detalle['exento'])){
                    $det->setExento($detalle['exento']);
                }
                $documento->setDetalle($det);
            }
            $dte = $documento->getDocumento($firma, $folio);
            AgregarDocumentoEnvio::dispatch($dte->saveXML(), $numero, $request->tipo, $contribuyente)->onQueue('documento');
            $montos = $dte->getTotales();
            if($ambiente == 0){
                $doc = new Documento();
                $doc->folio = $numero;
                $doc->tipo = $request->tipo;
                $doc->fecha = $fecha;
                $doc->ref_cliente = ($request->receptor != null)?$request->receptor['rut']: null;
                $doc->ref_contribuyente = $contribuyente->id;
                // Se obtienen los montos del documento y estos se guardan en la base de datos
                $doc->monto_exento = $montos['MntExe'];
                if(isset($montos['MntNeto']))
                    $doc->monto_neto = $montos['MntNeto'];
                else
                    $doc->monto_neto = 0;

                if(isset($montos['IVA']))
                    $doc->monto_iva = $montos['IVA'];
                else
                    $doc->monto_iva = 0;

                $doc->monto_total = $montos['MntTotal'];
                $doc->save();
            }
            $envio = new EnvioDte();
            $envio->agregar($dte);
            $envio->setFirma($firma);
            $envio->setCaratula([
                'RutEnvia' => $firma->getID(),
                'RutReceptor' => '60803000-K',
                'FchResol' => date('Y-m-d', strtotime(($ambiente==0)?$contribuyente->fch_resolucion_prod:$contribuyente->fch_resolucion_dev)),
                'NroResol' => ($ambiente==0)?$contribuyente->nro_resolucion_prod:$contribuyente->nro_resolucion_dev,
            ]);
            $envio_xml = $envio->generar();
            $trackid = $envio->enviar(null);
            if($trackid == 0){
                throw new Exception("El SII no respondio el envio.");
            }
            UploadDocumentoTmp::dispatch($envio_xml, $request->tipo, $numero, date('Y-m-d'), $contribuyente)->onQueue('documento');
            $pdf417 = new \BigFish\PDF417\PDF417();
            $pdf417->setColumns(12);
            $pdf417->setSecurityLevel(5);
            $data = $pdf417->encode($dte->getTED());

            $renderer = new \BigFish\PDF417\Renderers\ImageRenderer([
                'format' => 'data-url',
                'ratio' => 2,
                'scale' => 3,
                'padding' => 0
            ]);
            $generado = str_replace('data:image/png;base64,', '', $renderer->render($data));
            return [
                'PDF' => $generado,
                'folio' => $numero,
                'trackid' => $trackid,
                'fecha' => date('d/m/Y H:i', strtotime($fecha)),
                'sucsii' => Sii::getDireccionRegional($domicilio->comuna->nombre),
                'totales' => [
                    'exento' => $montos['MntExe'],
                    'neto' => (isset($montos['MntNeto'])) ? $montos['MntNeto'] : 0,
                    'iva' => (isset($montos['IVA'])) ? $montos['IVA'] : 0,
                    'total' => $montos['MntTotal']
                ]
            ];
        }catch(\Exception $ex){
            return $ex;
            return response()->json([
                'status' => 500,
                'error' => $ex->getMessage()
            ]);
        }
    }

    public function cargarFolios(Request $request){
        try{
            // Se valida la información recibida
            $validador = Validator::make($request->all(), [
                'contribuyente' => 'required|exists:contribuyentes,rut',
                'caf' => 'required|file',
            ]);

            if ($validador->fails()) {
                return response()->json([
                    'status' => 500,
                    'msg' => 'No se pudieron validar los datos',
                    'error' => $validador->errors()
                ]);
            }

            $file = $request->file('caf');
            $filePath=$file->getRealPath();
            $objXmlDocument = simplexml_load_file($filePath);
            if ($objXmlDocument === FALSE) {
                echo "There were errors parsing the XML file.\n";
                foreach(libxml_get_errors() as $error) {
                    echo $error->message;
                }
                exit;
            }

            $objJsonDocument = json_encode($objXmlDocument);
            $arrOutput = json_decode($objJsonDocument, TRUE);

            $rut = $arrOutput['CAF']['DA']['RE'];

            $contribuyente = Contribuyente::where('rut', $rut)->first();
            $tipo = $arrOutput['CAF']['DA']['TD'];
            $desde = $arrOutput['CAF']['DA']['RNG']['D'];
            $hasta = $arrOutput['CAF']['DA']['RNG']['H'];
            $fecha = $arrOutput['CAF']['DA']['FA'];
            $ldesde = CAF::where('tipo', '=', $tipo)
                                ->where('ref_contribuyente', $contribuyente->id)
                                ->orderBy('id', 'desc')
                                ->first();
            $ddesde = 0;
            $ddesde = ($ldesde == null) ? 0 : $ldesde->desde;

            if($ddesde < $desde){
                if($ldesde != null){
                    $ldesde->delete();
                }
                $caf = new CAF();
                $caf->tipo = $tipo;
                $caf->ref_contribuyente = $contribuyente->id;
                $caf->folio = $desde;
                $caf->xml = $objXmlDocument->saveXML();
                $caf->save();
            }else{
                return response()->json([
                    'status' => 500, 'error' => 'No se pueden cargar folios que sean menores a los ya cargados en el sistema. (Desde anterior: '.$ldesde
                ]);
            }

            return response()->json(['status' => 200, 'msg' => 'Folios cargados correctamente.']);
        }catch(\Exception $ex){
            Log::error($ex);
            return $ex;
            return response()->json([
                'status' => 500,
                'error' => $ex->getMessage()
            ]);
        }
    }

}
