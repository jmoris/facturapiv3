<?php

namespace App\Http\Controllers;

use App\Models\Contribuyente;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
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
}
