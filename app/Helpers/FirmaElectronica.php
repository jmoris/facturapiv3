<?php
namespace App\Helpers;

use App\Models\Contribuyente;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SolucionTotal\CoreDTE\FirmaElectronica as CoreDTEFirmaElectronica;
use stdClass;

class FirmaElectronica {

    public static function temporalPEM($user = null){
        $cert = [];
        if($user == null){
            $user = auth()->user();
        }
        $p12 = Storage::get('certificados/'.$user->id.'.p12');
        openssl_pkcs12_read($p12, $cert, $user->certpass);
        if (!Storage::exists('certificados/cert'.$user->id.'.crt.pem')) {
            Storage::put('certificados/cert'.$user->id.'.crt.pem',  $cert['cert']);
            Storage::put('certificados/cert'.$user->id.'.key.pem',  $cert['pkey']);
        }
        $urlcert = Storage::url('certificados/cert'.$user->id.'.crt.pem');
        $urlkey = Storage::url('certificados/cert'.$user->id.'.key.pem');
        $firma = new CoreDTEFirmaElectronica([
            'data' => $p12,
            'path_pkey' => storage_path('app/certificados/cert'.$user->id.'.key.pem'),
            'path_cert' => storage_path('app/certificados/cert'.$user->id.'.crt.pem'),
            'pass' => $user->certpass,
        ]);
        return $firma;
    }

    public static function statusCert(){
        try{
            $cert = [];
            $user = auth()->user();
            $p12 = Storage::get('certificados/'.$user->id.'.p12');
            openssl_pkcs12_read($p12, $cert, $user->certpass);
            $firma = new CoreDTEFirmaElectronica([
                'data' => $p12,
                'pass' => $user->certpass,
            ]);
            $hasta = $firma->getTo();
            if(date('Y-m-d H:i:s') < $hasta){
                return true;
            }else{
                return false;
            }
        }catch(Exception $ex){
            Log::error("El archivo del certificado no se encontro. Path: ".'certificados/'.$user->id.'.p12');
            return false;
        }
    }

}
