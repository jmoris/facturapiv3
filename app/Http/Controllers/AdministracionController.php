<?php

namespace App\Http\Controllers;

use App\Jobs\GenerarRCOF;
use App\Models\Contribuyente;
use App\Models\RCOF;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AdministracionController extends Controller
{
    public function attachContribuyente(Request $request){
        try{
            $validated = Validator::make($request->all(), [
                'contribuyente' => 'required|exists:contribuyentes,rut',
                'usuario' => 'required|exists:users,id'
            ]);

            if ($validated->fails()) {
                return response()->json([
                    'status' => 500,
                    'msg' => 'No se pudieron validar los datos',
                    'error' => $validated->errors()
                ]);
            }

            $user = User::find($request->usuario);
            $contribuyente = Contribuyente::where('rut', $request->contribuyente)->first();
            $user->contribuyentes()->sync($contribuyente->id);

            return response()->json([
                'status' => 200,
                'msg' => 'Contribuyente enlazado correctamente al usuario.'
            ]);
        }catch(Exception $ex){
            return response()->json([
                'status' => 500,
                'error' => $ex->getMessage()
            ]);
        }
    }

    public function detachContribuyente(Request $request){
        try{
            $validated = Validator::make($request->all(), [
                'contribuyente' => 'required|exists:contribuyentes,rut',
                'usuario' => 'required|exists:users,id'
            ]);

            if ($validated->fails()) {
                return response()->json([
                    'status' => 500,
                    'msg' => 'No se pudieron validar los datos',
                    'error' => $validated->errors()
                ]);
            }

            $user = User::find($request->usuario);
            $contribuyente = Contribuyente::where('rut', $request->contribuyente)->first();
            $user->contribuyentes()->detach($contribuyente->id);

            return response()->json([
                'status' => 200,
                'msg' => 'Contribuyente desenlazado correctamente del usuario.'
            ]);
        }catch(Exception $ex){
            return response()->json([
                'status' => 500,
                'error' => $ex->getMessage()
            ]);
        }
    }

    public function forceRCOF(){
        try{
            $c = 0;
            $contribuyentes = Contribuyente::all();
            foreach($contribuyentes as $contribuyente){
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
                        GenerarRCOF::dispatch($contribuyente, $usuario)->onQueue('documento');
                        Contribuyente::where('id', $contribuyente->id)->update([
                            'contador_boletas' => $contribuyente->contador_boletas + 5,
                        ]);
                        $c++;
                    }
                }
            }
            return response()->json([
                'status' => 200,
                'msg' => 'Se enviaron '.$c.' RCOFs correctamente.'
            ]);
        }catch(Exception $ex){
            Log::error($ex);
            return response()->json([
                'status' => 500,
                'msg' => 'Hubo problemas para generar los RCOF.'
            ]);
        }
    }
}
