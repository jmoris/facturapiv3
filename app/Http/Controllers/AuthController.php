<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function login(Request $request){
        try {
            $request->validate([
                'email' => 'email|required',
                'password' => 'required'
            ]);
            $credentials = request(['email', 'password']);
            if (!Auth::attempt($credentials)) {
                return response()->json([
                    'status' => 500,
                    'msg' => 'Unauthorized'
                ]);
            }
            $tokenResult = auth()->user()->createToken('authToken')->plainTextToken;
            return response()->json([
                'status' => 200,
                'access_token' => $tokenResult,
                'token_type' => 'Bearer',
            ]);
        } catch (Exception $error) {
            return response()->json([
                'status' => 500,
                'msg' => 'Error in Login',
                'error' => $error,
            ]);
        }
    }

    public function register(Request $request){
        try{
            $validador = Validator::make($request->all(), [
                'name' => 'required',
                'email' => 'required',
                'password' => 'required',
                'certificado' => 'required|file',
                'certpass' => 'required'
            ]);

            if($validador->fails()){
                return response()->json([
                    'status' => 500,
                    'msg' => 'No se pudo validar los parametros',
                    'error' => $validador->errors()
                ]);
            }

            $user = new User();
            $user->name = $request->name;
            $user->email = $request->email;
            $user->password = bcrypt($request->password);
            $user->certpass = $request->certpass;
            $user->save();

            $file = $request->certificado;
            Storage::putFileAs('certificados/', $file, $user->id.'.p12');

            return response()->json([
                'status' => 201,
                'msg' => 'Usuario guardado correctamente'
            ]);
        }catch(Exception $ex){
            return response()->json([
                'status' => 500,
                'msg' => 'Error al registrar usuario',
                'error' => $ex,
            ]);
        }

    }
}
