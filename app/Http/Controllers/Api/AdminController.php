<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyWallet;
use App\Models\Deposit;
use App\Models\Interest;
use App\Models\Transaction;
use App\Models\User;
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
                        'type' => $transaction->type,
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
                        'reference_number' => 'N/A',
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

    public function updateTransactionStatus(Request $request, $id)
    {
        $request->validate([
            'type' => 'required|in:deposit,withdrawal',
            'status' => 'required|in:pending,completed,failed',
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

    // Fetch all deposits with optional status filter
    public function memberDeposits(Request $request)
    {
        $perPage = $request->query('limit', 10);
        $page = $request->query('page', 1);
        $status = $request->query('status');

        $query = Deposit::with('user')->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $deposits = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => [
                'deposits' => $deposits->map(function ($deposit) {
                    return [
                        'id' => $deposit->id,
                        'date' => $deposit->created_at->format('Y-m-d'),
                        'reference_number' => $deposit->reference_number,
                        'network' => $deposit->network,
                        'status' => $deposit->status,
                        'amount' => number_format($deposit->amount, 2, '.', ''),
                        'user' => [
                            'id' => $deposit->user->id,
                            'first_name' => $deposit->user->first_name,
                            'last_name' => $deposit->user->last_name,
                        ],
                    ];
                })->toArray(),
                'pagination' => [
                    'current_page' => $deposits->currentPage(),
                    'total_pages' => $deposits->lastPage(),
                    'total_items' => $deposits->total(),
                    'limit' => $deposits->perPage(),
                ],
            ],
        ]);
    }

    // Record a withdrawal for a member
    public function recordWithdrawal(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0',
            'network' => 'required|string|max:255',
            'status' => 'required|in:pending,completed,failed',
        ]);

        $withdrawal = Withdrawal::create([
            'user_id' => $request->user_id,
            'amount' => $request->amount,
            'network' => $request->network,
            'status' => $request->status,
        ]);

        // Also record it as a transaction
        Transaction::create([
            'user_id' => $request->user_id,
            'amount' => $request->amount,
            'type' => 'withdrawal',
            'status' => $request->status,
            'network' => $request->network,
            'reference_number' => 'N/A',
            'date' => now()->format('Y-m-d'),
        ]);

        return response()->json([
            'message' => 'Withdrawal recorded successfully.',
            'data' => [
                'id' => $withdrawal->id,
                'user_id' => $withdrawal->user_id,
                'amount' => number_format($withdrawal->amount, 2, '.', ''),
                'network' => $withdrawal->network,
                'status' => $withdrawal->status,
            ],
        ], 201);
    }

    // Generate interest for members
    public function generateInterest(Request $request)
    {
        $request->validate([
            'rate' => 'required|numeric|min:0|max:100', // Interest rate in percentage (e.g., 5 for 5%)
        ]);

        $rate = $request->input('rate');
        $date = now()->format('Y-m-d');

        $users = User::where('role', 'user_client')->get();
        $interestRecords = [];

        foreach ($users as $user) {
            // Calculate total completed deposits for the user
            $baseAmount = Deposit::where('user_id', $user->id)
                ->where('status', 'completed')
                ->sum('amount');

            if ($baseAmount <= 0) {
                continue; // Skip users with no completed deposits
            }

            // Calculate interest
            $interestAmount = ($baseAmount * $rate) / 100;

            // Record the interest
            $interest = Interest::create([
                'user_id' => $user->id,
                'amount' => $interestAmount,
                'rate' => $rate,
                'base_amount' => $baseAmount,
                'date' => $date,
            ]);

            // Optionally, record the interest as a transaction
            Transaction::create([
                'user_id' => $user->id,
                'amount' => $interestAmount,
                'type' => 'interest',
                'status' => 'completed',
                'network' => 'N/A',
                'reference_number' => 'INT-' . $interest->id,
                'date' => $date,
            ]);

            $interestRecords[] = [
                'user_id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'base_amount' => number_format($baseAmount, 2, '.', ''),
                'interest_amount' => number_format($interestAmount, 2, '.', ''),
                'rate' => $rate,
            ];
        }

        return response()->json([
            'message' => 'Interest generated successfully for all eligible members.',
            'data' => $interestRecords,
        ]);
    }

    public function companyWallets(Request $request)
    {
        $perPage = $request->query('limit', 5);
        $page = $request->query('page', 1);
        $search = $request->query('search', '');

        $query = CompanyWallet::orderBy('created_at', 'desc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('network', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $wallets = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => [
                'wallets' => $wallets->map(function ($wallet) {
                    return [
                        'id' => $wallet->id,
                        'network' => $wallet->network,
                        'address' => $wallet->address,
                        'created_at' => $wallet->created_at->format('d/m/Y H:i:s'),
                    ];
                })->toArray(),
                'pagination' => [
                    'current_page' => $wallets->currentPage(),
                    'total_pages' => $wallets->lastPage(),
                    'total_items' => $wallets->total(),
                    'limit' => $wallets->perPage(),
                ],
            ],
        ]);
    }

    public function updateCompanyWallet(Request $request, $id)
    {
        $request->validate([
            'network' => 'required',
            'address' => 'required|string|max:255',
        ]);

        $wallet = CompanyWallet::findOrFail($id);
        $wallet->update([
            'network' => $request->network,
            'address' => $request->address,
        ]);

        return response()->json([
            'message' => 'Company wallet updated successfully.',
            'data' => [
                'id' => $wallet->id,
                'network' => $wallet->network,
                'address' => $wallet->address,
                'created_at' => $wallet->created_at->format('d/m/Y H:i:s'),
            ],
        ]);
    }

    public function createCompanyWallet(Request $request)
    {
        $request->validate([
            'network' => 'required',
            'address' => 'required|string|max:255',
        ]);

        $wallet = CompanyWallet::create([
            'network' => $request->network,
            'address' => $request->address,
        ]);

        return response()->json([
            'message' => 'Company wallet created successfully.',
            'data' => [
                'id' => $wallet->id,
                'network' => $wallet->network,
                'address' => $wallet->address,
                'created_at' => $wallet->created_at->format('d/m/Y H:i:s'),
            ],
        ], 201);
    }
}
