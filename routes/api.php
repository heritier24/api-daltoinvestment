<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\AuthenticationController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\NotificationController;
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
    Route::post('/user/update-password', [AuthenticationController::class, 'updatePassword']);
    Route::post('/pay-membership-fee', [AuthenticationController::class, 'payMembershipFee']);
    Route::get('/membership-status', [AuthenticationController::class, 'getMembershipStatus']);

    // Routes for company wallets and deposits
    Route::get('/company-wallets', [DepositController::class, 'getCompanyWallet']);
    Route::get('/company-wallets/networks', [DepositController::class, 'getNetworks']);
    Route::post('/deposits', [DepositController::class, 'createDeposit']);
    Route::get('/transactions', [DepositController::class, 'getUserTransactions']);
    Route::put('/deposits/{id}/reference', [DepositController::class, 'updateReferenceNumber']);
    Route::get('/total-completed-deposits', [DepositController::class, 'getTotalCompletedDeposits']);
    Route::get('withdrawals', [WithdrawController::class, 'withdrawals']);

    Route::get('daily-rois', [WithdrawController::class, 'dailyROIs']);

    Route::post('/request-withdrawal', [WithdrawController::class, 'requestWithdrawal']);

    Route::get('/user-roi', [WithdrawController::class, 'getUserROI']);

    Route::get('/user-wallet-amount', [WithdrawController::class, 'getWalletBalance']);

    Route::get('/user-transactions', [WithdrawController::class, 'getUserTransactions']);

    // Admin routes
    Route::prefix('admin')->group(function () {
        Route::get('/summary', [AdminController::class, 'getAdminSummary']);
        Route::get('/transactions', [AdminController::class, 'getTransactions']);
        Route::get('withdrawals/pending', [AdminController::class, 'pendingWithdrawals']);
        Route::get('deposits/pending', [AdminController::class, 'pendingDepositRequests']);
        Route::get('withdrawals/pending-requests', [AdminController::class, 'pendingWithdrawalRequests']);
        Route::patch('transactions/{id}/status', [AdminController::class, 'updateTransactionStatus']);
        Route::get('member-deposits', [AdminController::class, 'memberDeposits']);
        Route::post('record-withdrawal', [AdminController::class, 'recordWithdrawal']);
        Route::post('generate-interest', [AdminController::class, 'generateInterest']);
        Route::get('company-wallets', [AdminController::class, 'companyWallets']);
        Route::put('company-wallets/{id}', [AdminController::class, 'updateCompanyWallet']);
        Route::post('company-wallets', [AdminController::class, 'createCompanyWallet']);
        Route::get('company-interests', [AdminController::class, 'companyInterests']);
        Route::post('company-interests', [AdminController::class, 'createCompanyInterest']);
        Route::put('company-interests/{id}', [AdminController::class, 'updateCompanyInterest']);
        Route::delete('company-interests/{id}', [AdminController::class, 'deleteCompanyInterest']);
        Route::post('/generate-roi', [AdminController::class, 'generateROI']);
        Route::get('/pending-withdrawals', [AdminController::class, 'getPendingWithdrawals']);
        Route::post('/update-withdrawal-status/{withdrawalId}', [AdminController::class, 'updateWithdrawalStatus']);
        Route::get('withdrawals-admin', [AdminController::class, 'withdrawals']);
    });
});

Route::post('/register', [AuthenticationController::class, 'register']);
Route::get('/networks', [DepositController::class, 'getNetworks']);

Route::post('/login', [AuthenticationController::class, 'login']);
Route::get('/user/referred-users', [AuthenticationController::class, 'getReferredUsers']);
Route::post('/user/generate-referral-fees', [AuthenticationController::class, 'generateReferralFees']);

Route::post('/notifications', [NotificationController::class, 'create']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/{id}', [NotificationController::class, 'show']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
