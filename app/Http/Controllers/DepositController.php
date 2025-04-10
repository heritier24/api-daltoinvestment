<?php

namespace App\Http\Controllers;

use App\Models\CompanyWallet;
use App\Models\Deposit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DepositController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function getNetworks()
    {
        // dd('getNetworks');
        $networks = CompanyWallet::pluck('network'); // Fetch only the network names
        return response()->json([
            'status' => 'success',
            'data' => $networks,
        ], 200);
    }

    /**
 * Fetch the company wallet address for a given network.
 *
 * @param string $network
 * @return \Illuminate\Http\JsonResponse
 */
public function getWalletAddress($network)
{
    try {
        $companyWallet = CompanyWallet::where('network', $network)->first();

        if (!$companyWallet) {
            return response()->json([
                'message' => 'No company wallet found for the selected network.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'wallet_address' => $companyWallet->address,
            ],
            'message' => 'Company wallet address fetched successfully.',
        ]);
    } catch (\Exception $e) {
        Log::error('Error fetching company wallet address: ' . $e->getMessage(), [
            'network' => $network,
            'exception' => $e,
        ]);

        return response()->json([
            'message' => 'An error occurred while fetching the company wallet address.',
            'error' => $e->getMessage(),
        ], 500);
    }
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
        $user = Auth::user();
        // Check if the user has paid the membership fee
        if (!$user->membership_fee_paid) {
            return response()->json([
                'message' => 'You must pay the membership fee before making a deposit.',
            ], 403);
        }
        $request->validate([
            'network' => 'required|string|exists:company_wallets,network',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $deposit = Deposit::create([
            'user_id' => Auth::id(),
            'network' => $request->network,
            'amount' => $request->amount,
            'status' => 'pending',
            'reference_number' => null, // Will be updated after payment
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Deposit recorded successfully!',
            'data' => $deposit,
        ], 201);
    }

    public function getUserTransactions(Request $request)
    {
        $transactions = Deposit::where('user_id', Auth::id())
            ->select('id', 'created_at', 'reference_number', 'network', 'status', 'amount')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $transactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'date' => $transaction->created_at->format('d M Y, H:i'),
                    'reference_number' => $transaction->reference_number ?? 'N/A',
                    'network' => $transaction->network,
                    'status' => $transaction->status,
                    'amount' => number_format($transaction->amount, 2),
                ];
            }),
        ], 200);
    }

    public function updateReferenceNumber(Request $request, $id)
    {
        $request->validate([
            'reference_number' => 'required|string|max:255',
        ]);

        $deposit = Deposit::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$deposit) {
            return response()->json([
                'status' => 'error',
                'message' => 'Deposit not found or you do not have permission to update it.',
            ], 404);
        }

        if ($deposit->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot update reference number for a deposit that is not pending.',
            ], 403);
        }

        $deposit->reference_number = $request->reference_number;
        $deposit->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Reference number updated successfully!',
            'data' => [
                'id' => $deposit->id,
                'reference_number' => $deposit->reference_number,
            ],
        ], 200);
    }

    public function getTotalCompletedDeposits(Request $request)
    {
        $total = Deposit::where('user_id', Auth::id())
            ->where('status', 'completed')
            ->sum('amount');

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_completed_deposits' => number_format($total, 2, '.', ''),
            ],
        ], 200);
    }
}
