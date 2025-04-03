<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyInterest;
use App\Models\CompanyWallet;
use App\Models\DailyROI;
use App\Models\Deposit;
use App\Models\Interest;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawal;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth:api');
        $this->middleware('role:admin');
    }

    // Fetch admin summary data
    public function getAdminSummary(Request $request)
    {
        $user = Auth::user();

        if (!$user || $user->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        // Total completed deposits (from transactions table)
        $totalCompletedDeposits = Transaction::where('type', 'deposit')
            ->where('status', 'completed')
            ->sum('amount');

        // Total pending withdrawals (from withdrawals table)
        $totalPendingWithdrawals = Withdrawal::where('status', 'pending')
            ->sum('amount');

        // Total withdrawn (from transactions table)
        $totalWithdrawn = Transaction::where('type', 'withdraw')
            ->where('status', 'completed')
            ->sum('amount');

        // Total transactions amount (for progress calculations)
        $totalTransactions = Transaction::sum('amount');

        return response()->json([
            'data' => [
                'total_completed_deposits' => number_format($totalCompletedDeposits, 2, '.', ''),
                'total_pending_withdrawals' => number_format($totalPendingWithdrawals, 2, '.', ''),
                'total_withdrawn' => number_format($totalWithdrawn, 2, '.', ''),
                'total_transactions' => number_format($totalTransactions, 2, '.', ''),
            ],
        ]);
    }

    // Fetch transaction history (admin only)
    public function getTransactions(Request $request)
    {
        $user = Auth::user();

        if (!$user || $user->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $page = $request->query('page', 1);
        $limit = $request->query('limit', 10);
        $status = $request->query('status');

        $query = Transaction::with('user');

        if ($status) {
            $query->where('status', $status);
        }

        $totalItems = $query->count();
        $totalPages = max(1, ceil($totalItems / $limit));

        $transactions = $query->skip(($page - 1) * $limit)
            ->take($limit)
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'created_at' => $transaction->created_at->format('d/m/Y H:i:s'),
                    'network' => $transaction->network,
                    'status' => $transaction->status,
                    'amount' => number_format($transaction->amount, 2, '.', ''),
                    'user' => [
                        'first_name' => $transaction->user->first_name,
                        'last_name' => $transaction->user->last_name,
                    ],
                ];
            });

        return response()->json([
            'data' => [
                'transactions' => $transactions,
                'pagination' => [
                    'current_page' => (int) $page,
                    'total_pages' => $totalPages,
                    'total_items' => $totalItems,
                    'limit' => (int) $limit,
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
            'status' => 'required|in:pending,completed,failed',
        ]);

        $deposit = Deposit::findOrFail($id);
        $originalStatus = $deposit->status;
        $deposit->update([
            'status' => $request->status,
        ]);

        // If the status is updated to 'completed', create a transaction
        if ($request->status === 'completed' && $originalStatus !== 'completed') {
            $transaction = Transaction::create([
                'user_id' => $deposit->user_id,
                'type' => 'deposit',
                'amount' => $deposit->amount,
                'status' => 'completed',
                'network' => $deposit->network,
                'reference_number' => $deposit->reference_number,
                'date' => now()->format('Y-m-d'),
            ]);

            // Link the transaction to the deposit
            $deposit->update(['transaction_id' => $transaction->id]);
        }

        return response()->json([
            'message' => 'Deposit status updated successfully.',
            'data' => [
                'status' => $deposit->status,
            ],
        ]);
    }

    // Fetch all deposits with optional status filter
    public function memberDeposits(Request $request)
    {
        $perPage = $request->query('limit', 10);
        $page = $request->query('page', 1);
        $status = $request->query('status', 'all');

        $query = Deposit::query()->with('user');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $deposits = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => [
                'member_deposits' => $deposits->map(function ($deposit) {
                    return [
                        'id' => $deposit->id,
                        'user_id' => $deposit->user_id,
                        'amount' => number_format($deposit->amount, 2, '.', ''),
                        'status' => $deposit->status,
                        'created_at' => $deposit->created_at->format('d/m/Y H:i:s'),
                        'reference_number' => $deposit->reference_number ?? 'N/A',
                        'network' => $deposit->network ?? 'N/A',
                        'user' => [
                            'first_name' => $deposit->user->first_name ?? 'Unknown',
                            'last_name' => $deposit->user->last_name ?? 'Unknown',
                            'wallet_address' => $deposit->user->wallet_address ?? 'N/A', // Add wallet address
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

    public function companyInterests(Request $request)
    {
        $perPage = $request->query('limit', 5);
        $page = $request->query('page', 1);
        $search = $request->query('search', '');

        $query = CompanyInterest::orderBy('created_at', 'desc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('type', 'like', "%{$search}%")
                    ->orWhere('percentage', 'like', "%{$search}%");
            });
        }

        $interests = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => [
                'interests' => $interests->map(function ($interest) {
                    return [
                        'id' => $interest->id,
                        'type' => $interest->type,
                        'percentage' => number_format($interest->percentage, 2, '.', ''),
                        'status' => $interest->status,
                        'created_at' => $interest->created_at->format('d/m/Y H:i:s'),
                    ];
                })->toArray(),
                'pagination' => [
                    'current_page' => $interests->currentPage(),
                    'total_pages' => $interests->lastPage(),
                    'total_items' => $interests->total(),
                    'limit' => $interests->perPage(),
                ],
            ],
        ]);
    }

    public function createCompanyInterest(Request $request)
    {
        $request->validate([
            'type' => 'required|in:daily_investment,referral_fee|unique:company_interests,type',
            'percentage' => 'required|numeric|min:0|max:100',
            'status' => 'required|in:active,inactive',
        ]);

        $interest = CompanyInterest::create([
            'type' => $request->type,
            'percentage' => $request->percentage,
            'status' => $request->status,
        ]);

        return response()->json([
            'message' => 'Company interest created successfully.',
            'data' => [
                'id' => $interest->id,
                'type' => $interest->type,
                'percentage' => number_format($interest->percentage, 2, '.', ''),
                'status' => $interest->status,
                'created_at' => $interest->created_at->format('d/m/Y H:i:s'),
            ],
        ], 201);
    }

    public function updateCompanyInterest(Request $request, $id)
    {
        $request->validate([
            'type' => 'required|in:daily_investment,referral_fee|unique:company_interests,type,' . $id,
            'percentage' => 'required|numeric|min:0|max:100',
            'status' => 'required|in:active,inactive',
        ]);

        $interest = CompanyInterest::findOrFail($id);
        $interest->update([
            'type' => $request->type,
            'percentage' => $request->percentage,
            'status' => $request->status,
        ]);

        return response()->json([
            'message' => 'Company interest updated successfully.',
            'data' => [
                'id' => $interest->id,
                'type' => $interest->type,
                'percentage' => number_format($interest->percentage, 2, '.', ''),
                'status' => $interest->status,
                'created_at' => $interest->created_at->format('d/m/Y H:i:s'),
            ],
        ]);
    }

    public function deleteCompanyInterest($id)
    {
        $interest = CompanyInterest::findOrFail($id);
        $interest->delete();

        return response()->json([
            'message' => 'Company interest deleted successfully.',
        ]);
    }

    public function generateROI(Request $request)
    {
        $user = Auth::user();

        if (!$user || $user->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        // Validate the date input
        $request->validate([
            'date' => 'required|date|before_or_equal:today',
        ]);

        $date = Carbon::parse($request->date)->toDateString();

        // Check if ROI has already been generated for this date
        $existingROI = DailyROI::whereDate('date', $date)->exists();
        if ($existingROI) {
            return response()->json([
                'message' => 'ROI has already been generated for this date.',
            ], 400);
        }

        // Fetch the active daily investment interest rate
        $interest = CompanyInterest::where('type', 'daily_investment')
            ->where('status', 'active')
            ->first();

        if (!$interest) {
            return response()->json([
                'message' => 'No active daily investment interest rate found.',
            ], 400);
        }

        $dailyInterestRate = $interest->percentage / 100; // e.g., 1.5% -> 0.015

        // Fetch all completed deposits
        $deposits = Deposit::where('status', 'completed')->get();

        if ($deposits->isEmpty()) {
            return response()->json([
                'message' => 'No completed deposits found to generate ROI.',
            ], 400);
        }

        $roiGenerated = 0;
        foreach ($deposits as $deposit) {
            // Check if ROI for this deposit has already been calculated for this date
            $existingROIForDeposit = DailyROI::where('deposit_id', $deposit->id)
                ->where('date', $date)
                ->exists();

            if ($existingROIForDeposit) {
                Log::info("ROI already calculated for deposit ID {$deposit->id} on {$date}.");
                continue;
            }

            // Calculate the daily ROI
            $dailyROI = $deposit->amount * $dailyInterestRate;

            // Record the daily ROI
            DailyROI::create([
                'user_id' => $deposit->user_id,
                'deposit_id' => $deposit->id,
                'amount' => $dailyROI,
                'date' => $date,
                'created_at' => $date,
                'updated_at' => $date,
            ]);

            // Optionally, add the ROI to the user's wallet balance
            // $user = $deposit->user;
            // $user->wallet_balance += $dailyROI;
            // $user->save();

            $roiGenerated++;
            Log::info("Recorded daily ROI of {$dailyROI} for deposit ID {$deposit->id} on {$date}.");
        }

        return response()->json([
            'message' => "ROI generated successfully for $roiGenerated deposits on $date.",
        ], 201);
    }

    // Existing calculateDailyROI function (for scheduled tasks)
    public function calculateDailyROI()
    {
        // Skip if today is Saturday (6) or Sunday (0)
        if (Carbon::today()->dayOfWeek === Carbon::SATURDAY || Carbon::today()->dayOfWeek === Carbon::SUNDAY) {
            Log::info('Skipping ROI calculation: Today is a weekend.');
            return;
        }

        // Fetch the active daily investment interest rate
        $interest = CompanyInterest::where('type', 'daily_investment')
            ->where('status', 'active')
            ->first();

        if (!$interest) {
            Log::warning('No active daily investment interest rate found.');
            return;
        }

        $dailyInterestRate = $interest->percentage / 100; // e.g., 1.5% -> 0.015
        $today = Carbon::today()->toDateString();

        // Fetch all completed deposits
        $deposits = Deposit::where('status', 'completed')->get();

        foreach ($deposits as $deposit) {
            // Check if ROI for this deposit has already been calculated today
            $existingROI = DailyROI::where('deposit_id', $deposit->id)
                ->where('date', $today)
                ->exists();

            if ($existingROI) {
                Log::info("ROI already calculated for deposit ID {$deposit->id} on {$today}.");
                continue;
            }

            // Calculate the daily ROI
            $dailyROI = $deposit->amount * $dailyInterestRate;

            // Record the daily ROI
            DailyROI::create([
                'user_id' => $deposit->user_id,
                'deposit_id' => $deposit->id,
                'amount' => $dailyROI,
                'date' => $today,
            ]);

            // Optionally, add the ROI to the user's wallet balance
            $user = $deposit->user;
            $user->wallet_balance += $dailyROI;
            $user->save();

            Log::info("Recorded daily ROI of {$dailyROI} for deposit ID {$deposit->id} on {$today}.");
        }
    }

    // Fetch pending withdrawals (admin only)
    public function getPendingWithdrawals(Request $request)
    {
        $user = Auth::user();

        if (!$user || $user->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $search = $request->query('search');
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 5);
        $status = $request->query('status', 'pending');

        $query = Withdrawal::with('user')
            ->where('status', $status);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($q) use ($search) {
                    $q->where('username', 'like', "%{$search}%");
                })
                ->orWhere('network', 'like', "%{$search}%");
            });
        }

        $totalItems = $query->count();
        $totalPages = max(1, ceil($totalItems / $limit));

        $withdrawals = $query->skip(($page - 1) * $limit)
            ->take($limit)
            ->get()
            ->map(function ($withdrawal) {
                return [
                    'id' => $withdrawal->id,
                    'user' => [
                        'username' => $withdrawal->user->username,
                    ],
                    'created_at' => $withdrawal->created_at->format('d/m/Y H:i:s'),
                    'network' => $withdrawal->network,
                    'amount' => number_format($withdrawal->amount, 2, '.', ''),
                    'status' => $withdrawal->status,
                ];
            });

        return response()->json([
            'data' => [
                'withdrawals' => $withdrawals,
                'pagination' => [
                    'current_page' => (int) $page,
                    'total_pages' => $totalPages,
                    'total_items' => $totalItems,
                    'limit' => (int) $limit,
                ],
            ],
        ]);
    }

    // Update withdrawal status (admin only)
    public function updateWithdrawalStatus(Request $request, $withdrawalId)
    {
        $user = Auth::user();

        if (!$user || $user->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $request->validate([
            'status' => 'required|in:completed,failed',
        ]);

        $withdrawal = Withdrawal::findOrFail($withdrawalId);

        if ($withdrawal->status !== 'pending') {
            return response()->json([
                'message' => 'This withdrawal request has already been processed.',
            ], 400);
        }

        $withdrawal->status = $request->status;
        $withdrawal->save();

        // If status is 'completed', create a transaction record
        if ($request->status === 'completed') {
            Transaction::create([
                'user_id' => $withdrawal->user_id,
                'type' => 'withdraw',
                'amount' => $withdrawal->amount,
                'status' => 'completed',
                'network' => $withdrawal->network,
                'date' => now()->format('Y-m-d'),
            ]);
        }

        return response()->json([
            'message' => "Withdrawal request marked as {$request->status} successfully.",
        ]);
    }
}
