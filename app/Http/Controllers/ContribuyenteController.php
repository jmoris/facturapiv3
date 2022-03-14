<?php

namespace App\Http\Controllers;

use App\Helpers\FirmaElectronica as HelperFirmaElectronica;
use App\Models\Acteco;
use App\Models\Comuna;
use App\Models\ConfigContribuyente;
use App\Models\Contribuyente;
use App\Models\DireccionContribuyente;
use App\Models\InfoContribuyente;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use SolucionTotal\CoreDTE\FirmaElectronica;
use SolucionTotal\CoreDTE\Sii;

class ContribuyenteController extends Controller
{
    public function storeCertificado(Request $request){
        try{
            $validated = Validator::make($request->all(), [
                'contribuyente' => 'required',
                'certificado' => 'required',
                'password' => 'required',
            ]);
            if ($validated->fails()) {
                return response()->json([
                    'status' => 500,
                    'msg' => 'No se pudieron validar los datos',
                    'error' => $validated->errors()
                ]);
            }
            $contribuyente = Contribuyente::where('rut', $request->contribuyente)->first();
            if($contribuyente == null){
                return response()->json([
                    'status' => 500,
                    'msg' => 'El contribuyente no existe en la base de datos'
                ]);
            }
            $config = ConfigContribuyente::where('ref_contribuyente', $contribuyente->id)->first();
            if($config == null){
                $config = new ConfigContribuyente();
                $config->ref_contribuyente = $contribuyente->id;
            }
            $config->pass_certificado = $request->password;
            $config->save();
            $file = $request->certificado;
            if($file!=null){
                $path = 'contribuyentes/'.$contribuyente->id.'/';
                if (file_exists(storage_path($path.'cert.p12'))) {
                    unlink(storage_path($path.'cert.p12'));
                }
                Storage::putFileAs($path, $file, 'cert.p12');
            }else{
                $path = 'contribuyentes/'.$contribuyente->id.'/';
                Storage::putFileAs($path, $file, 'cert.p12');
            }
        }catch(Exception $ex){
            Log::erorr($ex);
            return response()->json([
                'status' => 500,
                'error' => 'Hubo un error al almacenar el certificado digital.'
            ]);
        }
        return response()->json([
            'status' => 200,
            'msg' => 'Certificado almacenado correctamente',
            'path' => Storage::url($path.'cert.p12')
        ]);
    }
    public function storeContribuyente(Request $request){
        try{
            $validated = Validator::make($request->all(), [
                'rut' => 'required|unique:contribuyentes',
                'telefono' => '',
                'mail' => '',
                'web' => '',
            ]);

            if ($validated->fails()) {
                return response()->json([
                    'status' => 500,
                    'msg' => 'No se pudieron validar los datos',
                    'error' => $validated->errors()
                ]);
            }
            $data = $request->only(['rut', 'telefono', 'mail', 'web']);
            $contribuyente = new Contribuyente();
            $contribuyente->rut = $request->rut;
            $contribuyente->razon_social = "RAZON SOCIAL";
            $contribuyente->ambiente = 1; // ambiente de certificacion
            $contribuyente->nro_resolucion_prod = 0;
            $contribuyente->fch_resolucion_prod = date('Y-m-d');
            $contribuyente->nro_resolucion_dev = 0;
            $contribuyente->fch_resolucion_dev = date('Y-m-d');
            $contribuyente->save();

            $firma = HelperFirmaElectronica::temporalPEM();
            $cookies = \SolucionTotal\CoreDTE\Sii\Autenticacion::requestCookies($firma);
            $info = Sii::getInfoCompletaContribuyente($request->rut, $cookies);
            $contribuyente = Contribuyente::where('rut', $request->rut)->first();
            $contribuyente->razon_social = $info['RAZONSOCIAL'];
            DireccionContribuyente::where('ref_contribuyente', $contribuyente->id)->delete();
            foreach($info['DIRECCIONES'] as $sucursal){
                $direccion = new DireccionContribuyente();
                $direccion->tipo = $sucursal['TIPO'];
                $direccion->direccion = $sucursal['DIRECCION'];
                $direccion->codigo = ((isset($sucursal['CODIGO'])?$sucursal['CODIGO']:null));
                $direccion->ref_contribuyente = $contribuyente->id;
                $nombre_comuna = ucwords(strtolower($sucursal['COMUNA']));
                $comuna = Comuna::where('nombre', \App\Helpers\Formatos::sanitizarComuna($nombre_comuna))->first();
                $direccion->ref_comuna = $comuna->id;
                $direccion->save();
            }
            $contribuyente->actecos()->detach();
            foreach($info['GIROS'] as $giro){
                $acteco = Acteco::find($giro['CODIGO']);
                $contribuyente->actecos()->attach($acteco);
            }
            Sii::setAmbiente(0);
            $res_prod = Sii::getAutorizacion($request->rut, $cookies);
            Sii::setAmbiente(1);
            $res_dev = Sii::getAutorizacion($request->rut, $cookies);
            if($res_prod != null){
                $contribuyente->nro_resolucion_prod = ($res_prod['numero']!=null)?$res_prod['numero']:0;
                $contribuyente->fch_resolucion_prod = ($res_prod['fecha']!=null)?date('Y-m-d', strtotime($res_prod['fecha'])):date('Y-m-d');
            }
            if($res_dev != null){
                $contribuyente->nro_resolucion_dev = $res_dev['numero'];
                $contribuyente->fch_resolucion_dev = date('Y-m-d', strtotime($res_dev['fecha']));
            }
            $contribuyente->save();

            return response()->json([
                'status' => 201,
                'msg' => 'Contribuyente guardado correctamente',
                'contribuyente' => Contribuyente::where('id', $contribuyente->id)->with('direcciones')->with('actecos')->first()
            ]);

        }catch(Exception $ex){
            Log::error($ex);
            return $ex;
            return response()->json([
                'status' => 500,
                'error' => $ex->getMessage()
            ]);
        }
    }

    public function getContribuyente(Request $request){
        try {
            $contribuyente = Contribuyente::where('rut', $request->contribuyente)->with('direcciones')->with('actecos')->first();
            return $contribuyente;
        }catch(Exception $ex){
            return $ex;
        }
    }

    public function getInfoContribuyente(Request $request){
        $firma = HelperFirmaElectronica::temporalPEM();
        $cookies = \SolucionTotal\CoreDTE\Sii\Autenticacion::requestCookies($firma);
        $info = Sii::getInfoContribuyente($request->rut, $cookies);
        return $info;
    }
}
