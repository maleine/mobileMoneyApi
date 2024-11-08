<?php
use App\Http\Controllers\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Models\Transaction;

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
Route::post('/account/deposit', [TransactionController::class, 'deposit']);
Route::post('/withdraw/initiate', [TransactionController::class, 'initiateWithdraw']);
Route::post('/withdraw/confirm', [TransactionController::class, 'confirmWithdraw']);
Route::get('/client/transactions', [TransactionController::class, 'getClientTransactions']);
Route::post('/account/transfer', [TransactionController::class, 'transfer']);

Route::post('/account/initiateWithdraw', [TransactionController::class, 'initiateWithdraw']);
Route::middleware('auth:api')->group(function () {





});


Route::post('/get-balance', [TransactionController::class, 'getBalance']);




Route::post('/users', [UserController::class, 'store']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
