<?php

namespace App\Http\Controllers;

use App\Models\CompanyWallet;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DepositController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // GET /api/company-wallets?network={network}
    public function getCompanyWallet(Request $request)
    {
        $network = $request->query('network');

        if (!$network) {
            return response()->json(['message' => 'Network is required'], 400);
        }

        $wallet = CompanyWallet::where('network', $network)->first();

        if (!$wallet) {
            return response()->json(['message' => 'Wallet not found for the specified network'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'network' => $wallet->network,
                'address' => $wallet->address,
            ],
        ]);
    }

    // POST /api/deposits
    public function createDeposit(Request $request)
    {
        $request->validate([
            'network' => 'required|string|in:BSC,TRC20',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $user = Auth::user();

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => 'deposit',
            'network' => $request->network,
            'amount' => $request->amount,
            'status' => 'pending',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Deposit recorded as pending. Please send the USDT to the company wallet address.',
            'data' => $transaction,
        ], 201);
    }
}
