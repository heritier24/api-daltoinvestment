<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginFormRequest;
use App\Http\Requests\RegisterFormRequest;
use App\Models\CompanyInterest;
use App\Models\CompanyWallet;
use App\Models\ReferralFees;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthenticationController extends Controller
{

    public function login(LoginFormRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Find the user by email
        $user = User::where('email', $validated['email'])->first();

        // Check if the user exists and the password is correct
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid email or password',
            ], 401);
        }

        // Generate a new Sanctum token for the user
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ], 201);
    }

    public function register(RegisterFormRequest $request): JsonResponse
    {
        // Get validated data
        $validated = $request->validated();

        // Find the referrer if a promocode is provided
        $referrer = null;
        if ($validated['promocode']) {
            $referrer = User::where('promocode', $validated['promocode'])->first();
        }

        // Create the new user
        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone_number' => $validated['phone_number'],
            'password' => Hash::make($validated['password']),
            'promocode' => 'REF_' . Str::random(8), // Generate a unique promocode for the new user
            'role' => $validated['role'] ?? 'user_client', // Default to user_client
            'networkaddress' => $validated['networkaddress'],
            'usdt_wallet' => $validated['usdt_wallet'],
            'referred_by' => $referrer ? $referrer->id : null,
        ]);

        // Generate a Sanctum token for the user
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ], 201);
    }

    public function getNetworks()
    {
        $networks = CompanyWallet::pluck('network')->toArray();

        return response()->json([
            'data' => $networks,
            'message' => 'Networks fetched successfully.',
        ]);
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 401);
        }

        // Validate the request
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Verify the current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
                'errors' => [
                    'current_password' => ['The current password is incorrect.'],
                ],
            ], 422);
        }

        // Update the password
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    public function user(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'data' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'role' => $user->role,
                'network' => $user->network,
                'networkaddress' => $user->networkaddress,
                'promocode' => $user->promocode,
            ],
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'phone_number' => 'required|string|max:20',
            'network' => 'required|in:TRON,Ethereum,Binance Smart Chain',
            'networkaddress' => 'required|string|max:255',
        ]);

        $user->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone_number' => $validated['phone_number'],
            'network' => $validated['network'],
            'networkaddress' => $validated['networkaddress'],
        ]);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'data' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'role' => $user->role,
                'network' => $user->network,
                'networkaddress' => $user->networkaddress,
                'promocode' => $user->promocode,
            ],
        ]);
    }

    public function totalCompletedDeposits(Request $request)
    {
        $user = $request->user();
        $total = $user->deposits()->where('status', 'completed')->sum('amount');

        return response()->json([
            'data' => [
                'total_completed_deposits' => number_format($total, 2, '.', ''),
            ],
        ]);
    }

    /**
     * Fetch referred users and their deposits for the user client dashboard.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getReferredUsers(Request $request)
    {
        try {
            // Validate the request
            $request->validate([
                'user_id' => 'required|exists:users,id',
            ]);

            // Check if the authenticated user matches the requested user_id
            // $authUser = Auth::user();

            // Fetch referred users with their transactions and referral fees
            $referredUsers = User::where('referred_by', $request->user_id)
                ->with(['transactions' => function ($query) {
                    $query->where('type', 'deposit')->with('referralFee');
                }])
                ->get()
                ->map(function ($referredUser) {
                    // Calculate total completed deposits
                    $completedDeposits = $referredUser->transactions
                        ->where('status', 'completed')
                        ->sum('amount');

                    // Get the status of the latest deposit
                    $latestDeposit = $referredUser->transactions->sortByDesc('created_at')->first();
                    $depositStatus = $latestDeposit ? $latestDeposit->status : 'N/A';

                    // Check if all completed deposits have referral fees generated
                    $completedDepositsWithFees = $referredUser->transactions
                        ->where('status', 'completed')
                        ->filter(function ($transaction) {
                            return $transaction->referralFee !== null;
                        })
                        ->count();

                    $totalCompletedDeposits = $referredUser->transactions->where('status', 'completed')->count();
                    $hasReferralFees = $totalCompletedDeposits > 0 && $completedDepositsWithFees === $totalCompletedDeposits;

                    return [
                        'id' => $referredUser->id,
                        'first_name' => $referredUser->first_name,
                        'last_name' => $referredUser->last_name,
                        'total_completed_deposits' => number_format($completedDeposits, 2, '.', ''),
                        'deposit_status' => $depositStatus,
                        'has_referral_fees' => $hasReferralFees,
                    ];
                });

            return response()->json([
                'data' => $referredUsers,
                'message' => 'Referred users fetched successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching referred users: ' . $e->getMessage(), [
                'user_id' => $request->user_id,
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'An error occurred while fetching referred users.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate referral fees for a referred user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateReferralFees(Request $request)
    {
        try {
            // Validate the request
            $request->validate([
                'user_id' => 'required|exists:users,id', // The logged-in user
                'referred_user_id' => 'required|exists:users,id', // The referred user
            ]);

            // Check if the authenticated user matches the requested user_id
            // $authUser = Auth::user();
            // if (!$authUser || $authUser->id != $request->user_id || $authUser->role !== 'user_client') {
            //     return response()->json([
            //         'message' => 'Unauthorized. You can only generate fees for your own referred users.',
            //     ], 403);
            // }

            // Verify that the referred user was referred by the authenticated user
            $referredUser = User::findOrFail($request->referred_user_id);
            if ($referredUser->referred_by != $request->user_id) {
                return response()->json([
                    'message' => 'This user was not referred by you.',
                ], 403);
            }

            // Fetch completed deposits that haven't had referral fees generated yet
            $completedDeposits = Transaction::where('user_id', $referredUser->id)
                ->where('type', 'deposit')
                ->where('status', 'completed')
                ->whereDoesntHave('referralFee')
                ->get();

            if ($completedDeposits->isEmpty()) {
                return response()->json([
                    'message' => 'No new completed deposits found to generate referral fees.',
                ]);
            }

            // Fetch the referral fee percentage
            $referralFeePercentage = CompanyInterest::where('type', 'referral_fee')->first();
            if (!$referralFeePercentage) {
                Log::warning('Referral fee percentage not found in company_interests table.');
                return response()->json([
                    'message' => 'Referral fee percentage not configured.',
                ], 500);
            }

            // Generate referral fees for each completed deposit
            $totalFeesGenerated = 0;
            foreach ($completedDeposits as $deposit) {
                $feeAmount = ($deposit->amount * $referralFeePercentage->percentage) / 100;

                ReferralFees::firstOrCreate(
                    [
                        'transaction_id' => $deposit->id,
                    ],
                    [
                        'referrer_id' => $request->user_id,
                        'referred_user_id' => $referredUser->id,
                        'deposit_amount' => $deposit->amount,
                        'fee_amount' => $feeAmount,
                    ]
                );

                $totalFeesGenerated += $feeAmount;
            }

            Log::info('Referral fees generated successfully.', [
                'referrer_id' => $request->user_id,
                'referred_user_id' => $referredUser->id,
                'total_fees_generated' => $totalFeesGenerated,
            ]);

            return response()->json([
                'message' => 'Referral fees generated successfully.',
                'total_fees_generated' => number_format($totalFeesGenerated, 2, '.', ''),
            ]);
        } catch (\Exception $e) {
            Log::error('Error generating referral fees: ' . $e->getMessage(), [
                'user_id' => $request->user_id,
                'referred_user_id' => $request->referred_user_id,
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'An error occurred while generating referral fees.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
