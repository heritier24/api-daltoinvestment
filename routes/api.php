<?php

use App\Http\Controllers\AuthenticationController;
use App\Http\Controllers\DepositController;
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

Route::middleware('auth:sanctum')->group(function () {
    // Route to get the authenticated user
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Routes for company wallets and deposits
    Route::get('/company-wallets', [DepositController::class, 'getCompanyWallet']);
    Route::get('/company-wallets/networks', [DepositController::class, 'getNetworks']);
    Route::post('/deposits', [DepositController::class, 'createDeposit']);
    Route::get('/transactions', [DepositController::class, 'getUserTransactions']);
    Route::put('/deposits/{id}/reference', [DepositController::class, 'updateReferenceNumber']);
    Route::get('/total-completed-deposits', [DepositController::class, 'getTotalCompletedDeposits']);
});

Route::post('/register', [AuthenticationController::class, 'register']);

Route::post('/login', [AuthenticationController::class, 'login']);
