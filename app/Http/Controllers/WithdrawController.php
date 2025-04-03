<?php

namespace App\Http\Controllers;

use App\Models\DailyROI;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WithdrawController extends Controller
{
    public function withdrawals(Request $request)
    {
        // $user = $request->user();
        $perPage = $request->query('limit', 5);
        $page = $request->query('page', 1);
        $search = $request->query('search', '');

        $query = Withdrawal::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('network', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%"); // Using ID as a proxy for reference number
            });
        }

        $withdrawals = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => [
                'withdrawals' => $withdrawals->map(function ($withdrawal) {
                    return [
                        'id' => $withdrawal->id,
                        'date' => $withdrawal->created_at->format('d/m/Y'),
                        'reference_number' => 'WDR-' . $withdrawal->id, // Generate a reference number
                        'network' => $withdrawal->network,
                        'networkaddress' => $withdrawal->user->networkaddress,
                        'amount' => number_format($withdrawal->amount, 2, '.', '') . ' USDT',
                        'status' => $withdrawal->status,
                    ];
                })->toArray(),
                'pagination' => [
                    'current_page' => $withdrawals->currentPage(),
                    'total_pages' => $withdrawals->lastPage(),
                    'total_items' => $withdrawals->total(),
                    'limit' => $withdrawals->perPage(),
                ],
            ],
        ]);
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

    public function requestWithdrawal(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        // Validate the request
        $request->validate([
            'amount' => 'required|numeric|min:10',
            'network' => 'required|in:TRON,Ethereum,BSC',
        ]);

        // Check for existing pending withdrawal
        $pendingWithdrawal = Withdrawal::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if ($pendingWithdrawal) {
            return response()->json([
                'message' => 'You already have a pending withdrawal request. Please wait for it to be processed.',
            ], 400);
        }

        // Calculate wallet balance
        $totalWithdrawals = Transaction::where('user_id', $user->id)
            ->where('type', 'withdraw')
            ->where('status', 'completed')
            ->sum('amount');

        $totalDailyROI = DailyROI::where('user_id', $user->id)
            ->sum('amount');

        $walletAmount = max(0, $totalDailyROI - $totalWithdrawals);

        // dump($walletAmount);

        if ($request->amount > $walletAmount) {
            return response()->json([
                'message' => 'Withdrawal amount exceeds your wallet balance. $' . number_format($walletAmount, 2, '.', '') . ' available.',
            ], 400);
        }

        $withdrawal = Withdrawal::create([
            'user_id' => $user->id,
            'amount' => $request->amount,
            'status' => 'pending',
            'network' => $request->network,
        ]);

        return response()->json([
            'message' => 'Withdrawal request submitted successfully.',
            'data' => [
                'withdrawal_id' => $withdrawal->id,
            ],
        ], 201);
    }

    public function getUserROI(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $search = $request->query('search');
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 5);

        $query = DailyROI::where('user_id', $user->id);

        if ($search) {
            $query->where('amount', 'like', "%{$search}%");
        }

        $totalItems = $query->count();
        $totalPages = max(1, ceil($totalItems / $limit));

        $roiData = $query->skip(($page - 1) * $limit)
            ->take($limit)
            ->get()
            ->map(function ($roi) {
                return [
                    'id' => $roi->id,
                    'date' => $roi->date instanceof \Carbon\Carbon
                    ? $roi->date->format('d/m/Y H:i:s')
                    : \Carbon\Carbon::parse($roi->date)->format('d/m/Y H:i:s'),
                    'amount' => number_format($roi->amount, 2, '.', ''),
                ];
            });

        return response()->json([
            'data' => [
                'roi_data' => $roiData,
                'pagination' => [
                    'current_page' => (int) $page,
                    'total_pages' => $totalPages,
                    'total_items' => $totalItems,
                    'limit' => (int) $limit,
                ],
            ],
        ]);
    }

    public function getWalletAmount(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        // Calculate wallet balance: sum of completed withdrawals - sum of daily ROI
        $totalWithdrawals = Transaction::where('user_id', $user->id)
            ->where('type', 'withdraw')
            ->where('status', 'completed')
            ->sum('amount');

        $totalDailyROI = DailyROI::where('user_id', $user->id)
            ->sum('amount');

        $walletAmount = max(0, $totalWithdrawals - $totalDailyROI);

        return response()->json([
            'data' => [
                'wallet_amount' => number_format($walletAmount, 2, '.', ''),
            ],
        ]);
    }

    public function getUserTransactions(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $type = $request->query('type', 'deposit');
        $search = $request->query('search');

        $query = Transaction::where('user_id', $user->id)
            ->where('type', $type);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('network', 'like', "%{$search}%");
            });
        }

        $transactions = $query->get()->map(function ($transaction) {
            return [
                'id' => $transaction->id,
                'date' => $transaction->date->format('d/m/Y H:i:s'),
                'reference_number' => $transaction->reference_number ?? 'N/A',
                'network' => $transaction->network ?? 'N/A',
                'status' => $transaction->status,
                'amount' => number_format($transaction->amount, 2, '.', ''),
            ];
        });

        return response()->json([
            'data' => $transactions,
        ]);
    }
}
