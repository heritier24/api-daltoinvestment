<?php

namespace App\Http\Controllers;

use App\Models\DailyROI;
use App\Models\ReferralFees;
use App\Models\Transaction;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WithdrawController extends Controller
{
    /**
     * Fetch the user's withdrawal transactions.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function withdrawals(Request $request)
    {
        try {
            $perPage = $request->query('limit', 5);
            $page = $request->query('page', 1);
            $search = $request->query('search', '');

            $query = Transaction::where('user_id', Auth::id())
                ->where('type', 'withdraw')
                ->orderBy('created_at', 'desc');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('network', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%");
                });
            }

            $withdrawals = $query->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'data' => [
                    'withdrawals' => $withdrawals->map(function ($withdrawal) {
                        return [
                            'id' => $withdrawal->id,
                            'date' => $withdrawal->created_at->format('d/m/Y'),
                            'referenceNumber' => 'WDR-' . $withdrawal->id, // Updated to camelCase
                            'network' => $withdrawal->network,
                            'networkAddress' => $withdrawal->user->networkaddress ?? 'N/A', // Updated to camelCase
                            'amount' => number_format($withdrawal->amount, 2, '.', '') . ' USDT',
                            'status' => $withdrawal->status,
                        ];
                    })->toArray(),
                    'pagination' => [
                        'currentPage' => $withdrawals->currentPage(), // Updated to camelCase
                        'totalPages' => $withdrawals->lastPage(), // Updated to camelCase
                        'totalItems' => $withdrawals->total(), // Updated to camelCase
                        'limit' => $withdrawals->perPage(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching withdrawals: ' . $e->getMessage(), [
                'userId' => Auth::id(), // Updated to camelCase
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'An error occurred while fetching withdrawals.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch the user's daily ROIs.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function dailyROIs(Request $request)
    {
        try {
            $perPage = $request->query('limit', 10);
            $page = $request->query('page', 1);
            $search = $request->query('search', '');

            $user = Auth::user();
            $query = DailyROI::with(['user'])->orderBy('date', 'desc');

            if ($user->role !== 'admin') {
                $query->where('user_id', $user->id); // Updated to camelCase
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
                            'userId' => $roi->user_id, // Updated to camelCase
                            'userEmail' => $roi->user ? $roi->user->email : 'N/A', // Updated to camelCase
                            'amount' => number_format($roi->amount, 2, '.', ''),
                            'date' => $roi->date instanceof \Carbon\Carbon
                            ? $roi->date->format('d/m/Y')
                            : \Carbon\Carbon::parse($roi->date)->format('d/m/Y'),
                            'createdAt' => $roi->created_at->format('d/m/Y H:i:s'), // Updated to camelCase
                        ];
                    })->toArray(),
                    'pagination' => [
                        'currentPage' => $rois->currentPage(), // Updated to camelCase
                        'totalPages' => $rois->lastPage(), // Updated to camelCase
                        'totalItems' => $rois->total(), // Updated to camelCase
                        'limit' => $rois->perPage(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching daily ROIs: ' . $e->getMessage(), [
                'userId' => Auth::id(), // Updated to camelCase
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'An error occurred while fetching daily ROIs.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch the user's wallet balance.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getWalletAmount()
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'user_client') {
                return response()->json([
                    'message' => 'Unauthorized. User client access required.',
                ], 403);
            }

            // Fetch total ROI
            $totalROI = $this->getTotalROI($user->id);
            $totalROI = (float) $totalROI;

            // Fetch total withdrawn amount (status: completed)
            $totalWithdrawn = Transaction::where('user_id', $user->id) // Updated to camelCase
                ->where('type', 'withdraw')
                ->where('status', 'completed')
                ->sum('amount');
            $totalWithdrawn = (float) $totalWithdrawn;

            // Fetch total referral fees
            $totalReferralFees = ReferralFees::where('referrer_id', $user->id) // Updated to camelCase
                ->sum('fee_amount'); // Updated to camelCase
            $totalReferralFees = (float) $totalReferralFees;

            // Log intermediate values for debugging
            Log::info('Calculating wallet balance for user', [
                'userId' => $user->id, // Updated to camelCase
                'totalROI' => $totalROI, // Already camelCase
                'totalWithdrawn' => $totalWithdrawn, // Already camelCase
                'totalReferralFees' => $totalReferralFees, // Updated to camelCase
            ]);

            // Calculate wallet balance
            $deduction = $totalROI + $totalReferralFees;
            $walletBalance = $deduction - $totalWithdrawn;

            // Ensure wallet balance is not negative
            $walletBalance = max(0, $walletBalance);

            // Log the final wallet balance
            Log::info('Wallet balance calculated', [
                'userId' => $user->id, // Updated to camelCase
                'walletBalance' => $walletBalance, // Already camelCase
            ]);

            return response()->json([
                'data' => [
                    'walletAmount' => number_format($walletBalance, 2, '.', ''), // Updated to camelCase
                ],
                'message' => 'Wallet balance fetched successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching wallet balance: ' . $e->getMessage(), [
                'userId' => Auth::id(), // Updated to camelCase
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'An error occurred while fetching wallet balance.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch the user's total ROI.
     *
     * @param int $userId
     * @return float
     */
    public function getTotalROI($userId)
    {
        try {
            $totalDailyROI = DailyROI::where('user_id', $userId) // Updated to camelCase
                ->sum('amount');

            $totalDailyROI = (float) $totalDailyROI;

            Log::info('Total ROI calculated', [
                'userId' => $userId, // Updated to camelCase
                'totalROI' => $totalDailyROI, // Updated to camelCase
            ]);

            return $totalDailyROI;
        } catch (\Exception $e) {
            Log::error('Error calculating total ROI: ' . $e->getMessage(), [
                'userId' => $userId, // Updated to camelCase
                'exception' => $e,
            ]);
            return 0.0;
        }
    }

    /**
     * Request a withdrawal.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function requestWithdrawal(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'user_client') {
                return response()->json([
                    'message' => 'Unauthorized. User client access required.',
                ], 403);
            }

            // Validate the request
            $request->validate([
                'amount' => 'required|numeric|min:10|max:1000000',
                'network' => 'required',
            ]);

            // Calculate wallet balance
            $totalROI = $this->getTotalROI($user->id);
            $totalWithdrawn = Transaction::where('user_id', $user->id) // Updated to camelCase
                ->where('type', 'withdraw')
                ->where('status', 'completed')
                ->sum('amount');
            $totalReferralFees = ReferralFees::where('referrer_id', $user->id) // Updated to camelCase
                ->sum('fee_amount'); // Updated to camelCase
            $deduction = (float) $totalROI + (float) $totalReferralFees;
            $walletBalance = max(0, (float) $deduction - $totalWithdrawn);

            // Validate withdrawal amount against wallet balance
            if ($request->amount > $walletBalance) {
                return response()->json([
                    'message' => "Insufficient wallet balance. Available: " . number_format($walletBalance, 2, '.', '') . " USDT",
                ], 400);
            }

            // Check for pending withdrawals
            $pendingWithdrawals = Transaction::where('user_id', $user->id) // Updated to camelCase
                ->where('type', 'withdraw')
                ->where('status', 'pending')
                ->count();
            if ($pendingWithdrawals > 0) {
                return response()->json([
                    'message' => 'You already have a pending withdrawal request. Please wait for it to be processed.',
                ], 400);
            }

            // Create the withdrawal transaction
            $transaction = Transaction::create([
                'user_id' => $user->id, // Updated to camelCase
                'type' => 'withdraw',
                'amount' => $request->amount,
                'status' => 'pending',
                'network' => $request->network,
                'reference_number' => 'WDR-' . time() . '-' . $user->id, // Updated to camelCase
                'date' => now()->format('Y-m-d H:i:s'), // Updated to camelCase
            ]);
            // Create the withdrawal record
            Withdrawal::create([
                'user_id' => $user->id,
                'amount' => $request->amount,
                'status' => 'pending',
                'network' => $request->network,
            ]);

            return response()->json([
                'message' => 'Withdrawal request submitted successfully. It is now pending approval.',
                'data' => [
                    'id' => $transaction->id,
                    'amount' => number_format($transaction->amount, 2, '.', ''),
                    'network' => $transaction->network,
                    'status' => $transaction->status,
                    'referenceNumber' => $transaction->referenceNumber, // Updated to camelCase
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error requesting withdrawal: ' . $e->getMessage(), [
                'user_id' => Auth::id(), // Updated to camelCase
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'An error occurred while requesting withdrawal.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch the user's ROI data.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserROI(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'message' => 'Unauthorized',
                ], 401);
            }

            $search = $request->query('search');
            $page = (int) $request->query('page', 1);
            $limit = (int) $request->query('limit', 5);

            $query = DailyROI::where('user_id', $user->id); // Updated to camelCase

            if ($search) {
                $query->where('amount', 'like', "%{$search}%");
            }

            $totalItems = $query->count();
            $totalPages = max(1, ceil($totalItems / $limit));

            $roiData = $query->orderBy('date', 'desc')
                ->skip(($page - 1) * $limit)
                ->take($limit)
                ->get()
                ->map(function ($roi) {
                    return [
                        'id' => $roi->id,
                        'date' => $roi->date instanceof \Carbon\Carbon
                        ? $roi->date->format('d/m/Y')
                        : \Carbon\Carbon::parse($roi->date)->format('d/m/Y'),
                        'amount' => number_format($roi->amount, 2, '.', ''),
                    ];
                });

            return response()->json([
                'data' => [
                    'roiData' => $roiData, // Updated to camelCase
                    'pagination' => [
                        'currentPage' => $page, // Updated to camelCase
                        'totalPages' => $totalPages, // Updated to camelCase
                        'totalItems' => $totalItems, // Updated to camelCase
                        'limit' => $limit,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching user ROI: ' . $e->getMessage(), [
                'user_id' => Auth::id(), // Updated to camelCase
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'An error occurred while fetching ROI data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch the user's transactions.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserTransactions(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'message' => 'Unauthorized',
                ], 401);
            }

            $type = $request->query('type', 'deposit');
            $search = $request->query('search');

            $query = Transaction::where('user_id', $user->id) // Updated to camelCase
                ->where('type', $type);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('referenceNumber', 'like', "%{$search}%") // Updated to camelCase
                        ->orWhere('network', 'like', "%{$search}%");
                });
            }

            $transactions = $query->orderBy('createdAt', 'desc') // Updated to camelCase
                ->get()
                ->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'date' => $transaction->createdAt->format('d/m/Y H:i:s'), // Updated to camelCase
                        'referenceNumber' => $transaction->referenceNumber ?? 'N/A', // Updated to camelCase
                        'network' => $transaction->network ?? 'N/A',
                        'status' => $transaction->status,
                        'amount' => number_format($transaction->amount, 2, '.', ''),
                    ];
                });

            return response()->json([
                'data' => $transactions,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching user transactions: ' . $e->getMessage(), [
                'userId' => Auth::id(), // Updated to camelCase
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'An error occurred while fetching transactions.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
