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
        // $this->middleware('auth:api');
        $this->middleware('role:admin');
    }

    public function summary()
    {
        $totalDeposits = Deposit::sum('amount');
        $totalCompletedDeposits = Deposit::where('status', 'completed')->sum('amount');
        $totalPendingDeposits = Deposit::where('status', 'pending')->sum('amount');
        $totalRewarded = 0.00; // Placeholder (implement this logic later)

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

    // New endpoint to fetch pending deposit requests
    public function pendingDepositRequests(Request $request)
    {
        $perPage = $request->query('limit', 10);
        $page = $request->query('page', 1);

        $pendingDeposits = Deposit::with('user')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => [
                'transactions' => $pendingDeposits->map(function ($deposit) {
                    return [
                        'id' => $deposit->id,
                        'date' => $deposit->created_at->format('Y-m-d'),
                        'reference_number' => $deposit->reference_number,
                        'network' => $deposit->network,
                        'status' => $deposit->status,
                        'amount' => number_format($deposit->amount, 2, '.', ''),
                        'type' => 'deposit',
                        'user' => [
                            'first_name' => $deposit->user->first_name,
                            'last_name' => $deposit->user->last_name,
                        ],
                    ];
                })->toArray(),
                'pagination' => [
                    'current_page' => $pendingDeposits->currentPage(),
                    'total_pages' => $pendingDeposits->lastPage(),
                    'total_items' => $pendingDeposits->total(),
                    'limit' => $pendingDeposits->perPage(),
                ],
            ],
        ]);
    }

    // New endpoint to fetch pending withdrawal requests
    public function pendingWithdrawalRequests(Request $request)
    {
        $perPage = $request->query('limit', 10);
        $page = $request->query('page', 1);

        $pendingWithdrawals = Withdrawal::with('user')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => [
                'transactions' => $pendingWithdrawals->map(function ($withdrawal) {
                    return [
                        'id' => $withdrawal->id,
                        'date' => $withdrawal->created_at->format('Y-m-d'),
                        'reference_number' => 'N/A', // Withdrawals may not have a reference number
                        'network' => $withdrawal->network,
                        'status' => $withdrawal->status,
                        'amount' => number_format($withdrawal->amount, 2, '.', ''),
                        'type' => 'withdrawal',
                        'user' => [
                            'first_name' => $withdrawal->user->first_name,
                            'last_name' => $withdrawal->user->last_name,
                        ],
                    ];
                })->toArray(),
                'pagination' => [
                    'current_page' => $pendingWithdrawals->currentPage(),
                    'total_pages' => $pendingWithdrawals->lastPage(),
                    'total_items' => $pendingWithdrawals->total(),
                    'limit' => $pendingWithdrawals->perPage(),
                ],
            ],
        ]);
    }

    // New endpoint to update a transaction's status
    public function updateTransactionStatus(Request $request, $id)
    {
        $request->validate([
            'type' => 'required|in:deposit,withdrawal',
            'status' => 'required|in:completed,failed',
        ]);

        $type = $request->input('type');
        $status = $request->input('status');

        if ($type === 'deposit') {
            $transaction = Deposit::findOrFail($id);
        } else {
            $transaction = Withdrawal::findOrFail($id);
        }

        $transaction->status = $status;
        $transaction->save();

        return response()->json([
            'message' => 'Transaction status updated successfully.',
            'data' => [
                'id' => $transaction->id,
                'status' => $transaction->status,
            ],
        ]);
    }
}
