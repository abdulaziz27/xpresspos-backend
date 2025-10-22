<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use App\Models\User;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * Handle user login and token generation
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'device_name' => 'nullable|string|max:255',
        ]);

        // Rate limiting
        $key = 'login.' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => ["Too many login attempts. Please try again in {$seconds} seconds."],
            ]);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            RateLimiter::hit($key, 300); // 5 minutes lockout
            
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if user account is active
        if ($user->store_id && $user->store && $user->store->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['Your store account is not active. Please contact support.'],
            ]);
        }

        // Clear rate limiting on successful login
        RateLimiter::clear($key);

        // Delete existing tokens for this user (optional - for single session)
        // $user->tokens()->delete();

        // Create new token with device name
        $deviceName = $request->device_name ?? 'API Token';
        $token = $user->createToken($deviceName, ['*'], now()->addWeek())->plainTextToken;

        // Load store relationship
        $user->load(['store']);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'store_id' => $user->store_id,
                    'store' => $user->store ? [
                        'id' => $user->store->id,
                        'name' => $user->store->name,
                        'status' => $user->store->status,
                    ] : null,
                    'roles' => $user->getRolesWithContext()->pluck('name'),
                    'permissions' => $user->getPermissionsWithContext()->pluck('name'),
                ],
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => now()->addWeek()->toISOString(),
            ],
            'message' => 'Login successful',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ]
        ]);
    }

    /**
     * Handle user logout and token revocation
     */
    public function logout(Request $request)
    {
        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Logout successful',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ]
        ]);
    }

    /**
     * Logout from all devices
     */
    public function logoutAll(Request $request)
    {
        // Revoke all tokens for this user
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Logged out from all devices successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ]
        ]);
    }

    /**
     * Get current authenticated user
     */
    public function me(Request $request)
    {
        $user = $request->user();
        
        // Load store relation first
        $user->load(['store']);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'store_id' => $user->store_id,
                    'store' => $user->store ? [
                        'id' => $user->store->id,
                        'name' => $user->store->name,
                        'status' => $user->store->status,
                    ] : null,
                    'roles' => $user->getRolesWithContext()->pluck('name'),
                    'permissions' => $user->getPermissionsWithContext()->pluck('name'),
                    'created_at' => $user->created_at->toISOString(),
                    'updated_at' => $user->updated_at->toISOString(),
                ],
            ],
            'message' => 'User data retrieved successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ]
        ]);
    }

    /**
     * Send password reset link
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Rate limiting for password reset
        $key = 'password-reset.' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => ["Too many password reset attempts. Please try again in {$seconds} seconds."],
            ]);
        }

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            RateLimiter::hit($key, 3600); // 1 hour lockout
            
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Password reset link sent to your email',
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ]
            ]);
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                // Revoke all existing tokens
                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Password reset successfully',
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ]
            ]);
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

    /**
     * Change password for authenticated user
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Optionally revoke all other tokens except current
        $currentToken = $request->user()->currentAccessToken();
        $user->tokens()->where('id', '!=', $currentToken->id)->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Password changed successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ]
        ]);
    }

    /**
     * Get user's active tokens/sessions
     */
    public function sessions(Request $request)
    {
        $user = $request->user();
        $currentTokenId = $request->user()->currentAccessToken()->id;

        $tokens = $user->tokens()->get()->map(function ($token) use ($currentTokenId) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'is_current' => $token->id === $currentTokenId,
                'last_used_at' => $token->last_used_at?->toISOString(),
                'created_at' => $token->created_at->toISOString(),
                'expires_at' => $token->expires_at?->toISOString(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'sessions' => $tokens,
                'total' => $tokens->count(),
            ],
            'message' => 'Active sessions retrieved successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ]
        ]);
    }

    /**
     * Revoke a specific token/session
     */
    public function revokeSession(Request $request, $tokenId)
    {
        $user = $request->user();
        $currentTokenId = $request->user()->currentAccessToken()->id;

        if ($tokenId == $currentTokenId) {
            throw ValidationException::withMessages([
                'token' => ['Cannot revoke current session. Use logout instead.'],
            ]);
        }

        $token = $user->tokens()->find($tokenId);

        if (!$token) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Session not found',
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ]
            ], 404);
        }

        $token->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Session revoked successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ]
        ]);
    }
}