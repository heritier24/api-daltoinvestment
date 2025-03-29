<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\AuthenticationController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\WithdrawController;
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

    Route::get('user', [AuthenticationController::class, 'user']);
    Route::post('logout', [AuthenticationController::class, 'logout']);
    Route::put('profile', [AuthenticationController::class, 'updateProfile']);

    // Routes for company wallets and deposits
    Route::get('/company-wallets', [DepositController::class, 'getCompanyWallet']);
    Route::get('/company-wallets/networks', [DepositController::class, 'getNetworks']);
    Route::post('/deposits', [DepositController::class, 'createDeposit']);
    Route::get('/transactions', [DepositController::class, 'getUserTransactions']);
    Route::put('/deposits/{id}/reference', [DepositController::class, 'updateReferenceNumber']);
    Route::get('/total-completed-deposits', [DepositController::class, 'getTotalCompletedDeposits']);
    Route::get('withdrawals', [WithdrawController::class, 'withdrawals']);
    

    // Admin routes
    Route::prefix('admin')->group(function () {
        Route::get('summary', [AdminController::class, 'summary']);
        Route::get('transactions', [AdminController::class, 'transactions']);
        Route::get('withdrawals/pending', [AdminController::class, 'pendingWithdrawals']);
        Route::get('deposits/pending', [AdminController::class, 'pendingDepositRequests']);
        Route::get('withdrawals/pending-requests', [AdminController::class, 'pendingWithdrawalRequests']);
        Route::patch('transactions/{id}/status', [AdminController::class, 'updateTransactionStatus']);
        Route::get('member-deposits', [AdminController::class, 'memberDeposits']);
        Route::post('record-withdrawal', [AdminController::class, 'recordWithdrawal']);
        Route::post('generate-interest', [AdminController::class, 'generateInterest']);
    });
});

Route::post('/register', [AuthenticationController::class, 'register']);

Route::post('/login', [AuthenticationController::class, 'login']);
