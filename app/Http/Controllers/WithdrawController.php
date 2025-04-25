<?php

namespace App\Http\Controllers;

use App\Models\DailyROI;
use App\Models\ReferralFees;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        $perPage = $request->query('limit', 5);
        $page = $request->query('page', 1);
        $search = $request->query('search', '');

        // Include the user relationship to access first_name and last_name
        $query = Withdrawal::with('user')
            ->where('user_id', Auth::id())
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
                    // Concatenate first_name and last_name to create full_name
                    $fullName = trim($withdrawal->user->first_name . ' ' . $withdrawal->user->last_name);

                    return [
                        'id' => $withdrawal->id,
                        'date' => $withdrawal->created_at->format('d/m/Y'),
                        'reference_number' => 'WDR-' . $withdrawal->id, // Generate a reference number
                        'network' => $withdrawal->network,
                        'networkaddress' => $withdrawal->user->networkaddress,
                        'amount' => number_format($withdrawal->amount, 2, '.', '') . ' USDT',
                        'status' => $withdrawal->status,
                        'full_name' => $fullName ?: 'N/A', // Fallback to 'N/A' if full_name is empty
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
     * Fetch the user's wallet balance with detailed breakdown.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getWalletBalance(int $userId)
    {
        try {
            // $user = Auth::user();
            // if (!$user || $user->role !== 'user_client') {
            //     return response()->json([
            //         'message' => 'Unauthorized. User client access required.',
            //     ], 403);
            // }

            // Fetch total ROI profit
            $totalROI = $this->getTotalROI($userId);
            $totalROI = (float) $totalROI;

            // Fetch total referral fees
            $totalReferralFees = ReferralFees::where('referrer_id', $userId)
                ->sum('fee_amount');
            $totalReferralFees = (float) $totalReferralFees;

            // Fetch total withdrawn amount (status: completed)
            $totalWithdrawn = Transaction::where('user_id', $userId)
                ->where('type', 'withdrawal') // Corrected 'withdraw' to 'withdrawal' to match the type used in requestWithdrawal
                ->where('status', 'completed')
                ->sum('amount');
            $totalWithdrawn = (float) $totalWithdrawn;

            // Calculate wallet balance
            $walletBalance = ($totalROI + $totalReferralFees) - $totalWithdrawn;
            $walletBalance = max(0, $walletBalance); // Ensure balance is not negative

            // Log intermediate values for debugging
            Log::info('Calculating wallet balance for user', [
                'user_id' => $userId,
                'total_roi' => $totalROI,
                'total_referral_fees' => $totalReferralFees,
                'total_withdrawn' => $totalWithdrawn,
                'wallet_balance' => $walletBalance,
            ]);

            return response()->json([
                'data' => [
                    'wallet_balance' => number_format($walletBalance, 2, '.', ''),
                    'total_roi' => number_format($totalROI, 2, '.', ''),
                    'total_referral_fees' => number_format($totalReferralFees, 2, '.', ''),
                    'total_withdrawn' => number_format($totalWithdrawn, 2, '.', ''),
                ],
                'message' => 'Wallet balance fetched successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching wallet balance: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'An error occurred while fetching wallet balance.',
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
            $totalDailyROI = DailyROI::where('user_id', $userId)
                ->sum('amount');

            $totalDailyROI = (float) $totalDailyROI;

            if ($totalDailyROI < 0) {
                Log::warning('Total ROI is negative', [
                    'user_id' => $userId,
                    'total_roi' => $totalDailyROI,
                ]);
                $totalDailyROI = 0.0; // Prevent negative ROI
            }

            Log::info('Total ROI calculated', [
                'user_id' => $userId,
                'total_roi' => $totalDailyROI,
            ]);

            return $totalDailyROI;
        } catch (\Exception $e) {
            Log::error('Error calculating total ROI: ' . $e->getMessage(), [
                'user_id' => $userId,
                'exception' => $e,
            ]);
            return 0.0;
        }
    }

    /**
     * Request a withdrawal for the authenticated user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function requestWithdrawal(Request $request)
    {
        try {
            $user = $request->user_id;
            // if (!$user || $user->role !== 'user_client') {
            //     return response()->json([
            //         'message' => 'Unauthorized. User client access required.',
            //     ], 403);
            // }

            // Validate the request
            $request->validate([
                'amount' => 'required|numeric|min:10',
                'network' => 'required|string',
                'wallet_address' => 'required|string|max:255',
            ]);

            // Fetch wallet balance
            $walletBalanceResponse = $this->getWalletBalance($request->user_id);
            if ($walletBalanceResponse->getStatusCode() !== 200) {
                return $walletBalanceResponse; // Return error if fetching balance fails
            }

            $walletBalanceData = $walletBalanceResponse->getData(true)['data'];
            $walletBalance = (float) $walletBalanceData['wallet_balance'];

            if ($request->amount > $walletBalance) {
                return response()->json([
                    'message' => "Amount exceeds wallet balance ($walletBalance USDT).",
                ], 400);
            }

            // Check if the user has a pending withdrawal
            $existingWithdrawal = Transaction::where('user_id', $request->user_id)
                ->where('type', 'withdrawal')
                ->where('status', 'pending')
                ->first();

            if ($existingWithdrawal) {
                return response()->json([
                    'message' => 'You have a pending withdrawal being processed. Please wait for it to complete.',
                ], 400);
            }

            // Begin transaction to ensure data consistency
            DB::beginTransaction();

            // Create the withdrawal transaction
            $transaction = Transaction::create([
                'user_id' => $request->user_id,
                'type' => 'withdrawal',
                'amount' => $request->amount,
                'network' => $request->network,
                'wallet_address' => $request->wallet_address,
                'status' => 'pending',
                'reference_number' => 'WDR-' . time() . '-' . $request->user_id,
                'date' => now()->format('Y-m-d H:i:s'),
            ]);

            // Create the withdrawal record
            Withdrawal::create([
                'user_id' => $request->user_id,
                'amount' => $request->amount,
                'status' => 'pending',
                'network' => $request->network,
            ]);

            $user = User::where('id', $request->user_id)->first();

            // Update the user's profile with the network and wallet address
            $user->networkaddress = $request->network;
            $user->usdt_wallet = $request->wallet_address;
            $user->save();

            DB::commit();

            Log::info('Withdrawal request submitted', [
                'user_id' => $request->user_id,
                'transaction_id' => $transaction->id,
                'amount' => $request->amount,
            ]);

            return response()->json([
                'message' => 'Withdrawal request submitted successfully. It is now pending approval.',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error requesting withdrawal: ' . $e->getMessage(), [
                'user_id' => $request->user_id,
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'An error occurred while requesting the withdrawal.',
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
