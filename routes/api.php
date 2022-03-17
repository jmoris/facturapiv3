<?php

use App\Helpers\FirmaElectronica;
use App\Http\Controllers\AdministracionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContribuyenteController;
use App\Http\Controllers\DocumentoController;
use App\Jobs\DescargarActecos;
use App\Models\Acteco;
use App\Models\Documento;
use App\Models\InfoContribuyente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use SolucionTotal\CoreDTE\Sii;
use SolucionTotal\CoreDTE\Sii\Autenticacion;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('statuscert', function(){
        return (FirmaElectronica::statusCert())? 'vigente': 'invalido';
    });

    /* BOLETAS */
    Route::post('boleta', [DocumentoController::class, 'storeBoleta']);
    Route::get('boleta', [DocumentoController::class, 'getBoletas']);
    Route::post('boleta/consulta', [DocumentoController::class, 'consultaEnvioBoleta']);
    Route::post('boleta/reimpresion', [DocumentoController::class, 'reimprimirBoleta']);
    Route::post('certificacion/boletas', [CertificacionController::class, 'setPruebaBoletas']);
    Route::get('boleta/reportefolios', [DocumentoController::class, 'getReporteFolios']);

    /* DOCUMENTOS */
    Route::post('documento', [DocumentoController::class, 'storeDocumento']);
    Route::get('documento', [DocumentoController::class, 'getDocumentos']);
    Route::get('documento/obtener', [DocumentoController::class, 'getDocumento']);
    Route::post('documento/consulta', [DocumentoController::class, 'consultaDocumento']);
    Route::post('documento/reimpresion', [DocumentoController::class, 'reimprimirDocumento']);
    Route::post('documento/folios', [DocumentoController::class, 'cargarFolios']);
    Route::post('documento/ver', [DocumentoController::class, 'generarPDF']);
    Route::post('documento/verxml', [DocumentoController::class, 'generarPDFdeXML']);

    /* CONTRIBUYENTES */
    Route::get('informacion', [ContribuyenteController::class, 'getContribuyente']);
    Route::get('infocontribuyente', [ContribuyenteController::class, 'getInfoContribuyente']);

});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::post('contribuyentes', [ContribuyenteController::class, 'storeContribuyente']);
    Route::post('attachuser', [AdministracionController::class, 'attachContribuyente']);
    Route::post('detachuser', [AdministracionController::class, 'detachContribuyente']);
    Route::post('cambiarambiente', [AdministracionController::class, 'changeAmbiente']);
    Route::post('forzarrcof', [AdministracionController::class, 'forceRCOF']);
});
