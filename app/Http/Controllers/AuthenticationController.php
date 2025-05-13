<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginFormRequest;
use App\Http\Requests\RegisterFormRequest;
use App\Models\CompanyInterest;
use App\Models\CompanyWallet;
use App\Models\MembershipFees;
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
     * Fetch referred users and their deposits with referral fees for the user client dashboard.
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
        $authUser = Auth::user();
        // if (!$authUser || $authUser->id != $request->user_id) {
        //     return response()->json([
        //         'message' => 'Unauthorized.',
        //     ], 403);
        // }

        // Fetch referred users with their transactions and referral fees
        $referredUsers = User::where('referred_by', $request->user_id)
            ->with(['transactions' => function ($query) {
                $query->where('type', 'deposit');
            }, 'transactions.referralFees' => function ($query) use ($request) {
                $query->where('referrer_id', $request->user_id);
            }])
            ->get();

        // Flatten the data to create a row for each deposit
        $depositRows = $referredUsers->flatMap(function ($referredUser) use ($request) {
            return $referredUser->transactions->map(function ($transaction) use ($referredUser, $request) {
                $referralFee = $transaction->referralFees->first();
                $hasReferralFees = !is_null($referralFee);

                return [
                    'user_id' => $referredUser->id,
                    'first_name' => $referredUser->first_name,
                    'last_name' => $referredUser->last_name,
                    'deposit_id' => $transaction->id,
                    'deposit_amount' => number_format($transaction->amount, 2, '.', ''),
                    'deposit_status' => $transaction->status,
                    'deposit_created_at' => $transaction->created_at->toDateTimeString(),
                    'referral_fee' => $referralFee ? number_format($referralFee->fee_amount, 2, '.', '') : '0.00',
                    'has_referral_fees' => $hasReferralFees,
                ];
            });
        })->values();

        // Calculate total referral fees across all deposits
        $totalReferralFeesEarned = $referredUsers->flatMap(function ($referredUser) {
            return $referredUser->transactions->where('status', 'completed');
        })->sum(function ($transaction) {
            $referralFee = $transaction->referralFees->first();
            return $referralFee ? $referralFee->fee_amount : 0;
        });

        return response()->json([
            'data' => [
                'deposit_rows' => $depositRows,
                'total_referral_fees_earned' => number_format($totalReferralFeesEarned, 2, '.', ''),
            ],
            'message' => 'Referred users deposits fetched successfully.',
        ]);
    } catch (\Exception $e) {
        Log::error('Error fetching referred users deposits: ' . $e->getMessage(), [
            'user_id' => $request->user_id,
            'exception' => $e,
        ]);

        return response()->json([
            'message' => 'An error occurred while fetching referred users deposits.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    /**
     * Generate referral fees for a referred user's completed deposits.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateReferralFees(Request $request)
{
    try {
        // Validate the request
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'referred_user_id' => 'required|exists:users,id',
            'deposit_id' => 'required|exists:transactions,id',
        ]);

        // Check if the authenticated user matches the requested user_id
        $authUser = Auth::user();
        // if (!$authUser || $authUser->id != $request->user_id) {
        //     return response()->json([
        //         'message' => 'Unauthorized.',
        //     ], 403);
        // }

        // Fetch the referred user
        $referredUser = User::findOrFail($request->referred_user_id);
        // if ($referredUser->referred_by != Auth::id()) {
        //     return response()->json([
        //         'message' => 'This user was not referred by you.',
        //     ], 403);
        // }

        // Fetch the specific deposit
        $deposit = Transaction::where('id', $request->deposit_id)
            ->where('user_id', $referredUser->id)
            ->where('type', 'deposit')
            ->where('status', 'completed')
            ->first();

        if (!$deposit) {
            return response()->json([
                'message' => 'Deposit not found or not eligible for referral fees.',
            ], 404);
        }

        // Check if referral fees have already been generated for this deposit
        $existingFee = ReferralFees::where('transaction_id', $deposit->id)
            ->where('referrer_id', $request->user_id)
            ->first();

        if ($existingFee) {
            return response()->json([
                'message' => 'Referral fees have already been generated for this deposit.',
            ], 400);
        }

        // Calculate referral fee (6% as per previous context)
        $referralFeePercentage = CompanyInterest::where('type', 'referral_fee')->value('percentage');
        $percentage = $referralFeePercentage / 100;
        $feeAmount = $deposit->amount * $percentage;

        // Create the referral fee record
        ReferralFees::create([
            'referrer_id' => $request->user_id,
            'referred_user_id' => $referredUser->id,
            'transaction_id' => $deposit->id,
            'deposit_amount' => $deposit->amount,
            'fee_amount' => $feeAmount,
        ]);

        // Update the referrer's balance (if you have a balance system)
        // $authUser->balance = ($authUser->balance ?? 0) + $feeAmount;
        // $authUser->save();

        return response()->json([
            'message' => 'Referral fee generated successfully.',
            'total_fees_generated' => number_format($feeAmount, 2, '.', ''),
        ]);
    } catch (\Exception $e) {
        Log::error('Error generating referral fees: ' . $e->getMessage(), [
            'user_id' => $request->user_id,
            'referred_user_id' => $request->referred_user_id,
            'deposit_id' => $request->deposit_id,
            'exception' => $e,
        ]);

        return response()->json([
            'message' => 'An error occurred while generating referral fees.',
            "error" => $e->getMessage(),
        ], 500);
    }
}

    /**
     * Check the user's membership status and profile details.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMembershipStatus()
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'message' => 'Unauthorized',
                ], 401);
            }

            return response()->json([
                'data' => [
                    'membership_fee_paid' => $user->membership_fee_paid,
                    'membership_fee_amount' => config('app.membership_fee', 50.00),
                    'network' => $user->network, // Assuming 'network' column exists in users table
                    'wallet_address' => $user->networkaddress, // Assuming 'networkaddress' column exists
                ],
                'message' => 'Membership status fetched successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching membership status: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'An error occurred while fetching membership status.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Pay the membership fee and update user profile.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function payMembershipFee(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'user_client') {
                return response()->json([
                    'message' => 'Unauthorized. User client access required.',
                ], 403);
            }

            // Check if the user has already paid the membership fee
            if ($user->membership_fee_paid) {
                return response()->json([
                    'message' => 'Membership fee already paid.',
                ], 400);
            }

            // Validate the request
            $request->validate([
                'amount' => 'required',
                'network' => 'required', // We'll validate against available networks in the frontend
                'company_wallet_address' => 'required|string|max:255',
            ]);

            // Create the membership fee transaction
            $membershipFee = MembershipFees::create([
                'user_id' => $user->id,
                'amount' => $request->amount,
                'network' => $request->network,
                'wallet_address' => $request->company_wallet_address,
                'status' => 'pending',
                'reference_number' => 'MEM-' . time() . '-' . $user->id,
            ]);

            // Update the user's membership status and profile
            $user->membership_fee_paid = true;
            $user->save();

            // Update the membership fee status to completed (for simplicity)
            // $membershipFee->status = 'completed';
            // $membershipFee->save();

            return response()->json([
                'message' => 'Membership fee paid successfully.',
                'data' => [
                    'id' => $membershipFee->id,
                    'amount' => number_format($membershipFee->amount, 2, '.', ''),
                    'network' => $membershipFee->network,
                    'wallet_address' => $membershipFee->wallet_address,
                    'status' => $membershipFee->status,
                    'reference_number' => $membershipFee->reference_number,
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error paying membership fee: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'An error occurred while paying the membership fee.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
