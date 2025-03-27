<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\Withdrawal;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:admin');
    }

    public function summary()
    {
        $totalDeposits = Deposit::sum('amount');
        $totalCompletedDeposits = Deposit::where('status', 'completed')->sum('amount');
        $totalPendingDeposits = Deposit::where('status', 'pending')->sum('amount');
        $totalRewarded = 5000.00; // Placeholder (implement this logic later)

        return response()->json([
            'data' => [
                'total_deposits' => number_format($totalDeposits, 2, '.', ''),
                'total_completed_deposits' => number_format($totalCompletedDeposits, 2, '.', ''),
                'total_pending_deposits' => number_format($totalPendingDeposits, 2, '.', ''),
                'total_rewarded' => number_format($totalRewarded, 2, '.', ''),
            ],
        ]);
    }

    public function transactions(Request $request)
    {
        $perPage = $request->query('limit', 10);
        $page = $request->query('page', 1);

        $transactions = Transaction::with('user')
            ->orderBy('date', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => [
                'transactions' => $transactions->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'date' => $transaction->date,
                        'reference_number' => $transaction->reference_number,
                        'network' => $transaction->network,
                        'status' => $transaction->status,
                        'amount' => number_format($transaction->amount, 2, '.', ''),
                        'user' => [
                            'first_name' => $transaction->user->first_name,
                            'last_name' => $transaction->user->last_name,
                        ],
                    ];
                })->toArray(),
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'total_pages' => $transactions->lastPage(),
                    'total_items' => $transactions->total(),
                    'limit' => $transactions->perPage(),
                ],
            ],
        ]);
    }

    public function pendingWithdrawals()
    {
        $totalPendingWithdrawals = Withdrawal::where('status', 'pending')->sum('amount');

        return response()->json([
            'data' => [
                'total_pending_withdrawals' => number_format($totalPendingWithdrawals, 2, '.', ''),
            ],
        ]);
    }
}
