<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginFormRequest;
use App\Http\Requests\RegisterFormRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            'usdt_wallet' => $validated['usdt_wallet'],
            'referred_by' => $referrer ? $referrer->id : null,
        ]);

        // Generate a Sanctum token for the user
        $token = $user->createToken('auth_token')->plainTextToken;

        // Return the response
        return response()->json([
            'status' => 'success',
            'message' => 'User registered successfully',
            'data' => [
                'user' => $user,
                'token' => $token,
                'referral_link' => url('/register?ref=' . $user->promocode),
            ],
        ], 201);
    }
}
