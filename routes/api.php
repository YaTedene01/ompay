<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\QrController;

Route::post('auth/envoyer-lien', [AuthController::class, 'sendLink']);
Route::post('auth/echange', [AuthController::class, 'echange']);

Route::middleware('auth:api')->group(function () {
    Route::get('user', function (Request $request) { return $request->user(); });
    Route::get('compte/dashboard', [\App\Http\Controllers\Api\CompteController::class, 'dashboard']);
    Route::get('compte/solde', [\App\Http\Controllers\Api\CompteController::class, 'solde']);
    Route::post('compte/paiement', [\App\Http\Controllers\Api\CompteController::class, 'paiement']);
    Route::post('compte/transfert', [\App\Http\Controllers\Api\CompteController::class, 'transfert']);
    Route::get('compte/transactions', [\App\Http\Controllers\Api\CompteController::class, 'transactions']);
});
