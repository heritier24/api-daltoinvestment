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
use Illuminate\Support\Facades\Log;

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

    public function dailyROIs(Request $request)
    {
        $perPage = $request->query('limit', 10);
        $page = $request->query('page', 1);
        $search = $request->query('search', '');

        $user = auth()->user();
        $query = DailyROI::with(['user', 'deposit'])->orderBy('date', 'desc');

        // If the user is not an admin, restrict to their own ROIs
        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('amount', 'like', "%{$search}%")
                  ->orWhere('date', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($q) use ($search) {
                      $q->where('email', 'like', "%{$search}%");
                  });
            });
        }

        $rois = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => [
                'rois' => $rois->map(function ($roi) {
                    return [
                        'id' => $roi->id,
                        'user_id' => $roi->user_id,
                        'user_email' => $roi->user ? $roi->user->email : 'N/A',
                        'deposit_id' => $roi->deposit_id,
                        'deposit_amount' => $roi->deposit ? number_format($roi->deposit->amount, 2, '.', '') : 'N/A',
                        'amount' => number_format($roi->amount, 2, '.', ''),
                        'date' => $roi->date,
                        'created_at' => $roi->created_at->format('d/m/Y H:i:s'),
                    ];
                })->toArray(),
                'pagination' => [
                    'current_page' => $rois->currentPage(),
                    'total_pages' => $rois->lastPage(),
                    'total_items' => $rois->total(),
                    'limit' => $rois->perPage(),
                ],
            ],
        ]);
    }
}
