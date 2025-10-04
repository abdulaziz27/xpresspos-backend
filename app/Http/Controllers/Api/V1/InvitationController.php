<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class InvitationController extends Controller
{
    /**
     * Get invitation details by token.
     */
    public function show(string $token): JsonResponse
    {
        $invitation = StaffInvitation::withoutStoreScope()->where('token', $token)->first();

        if (!$invitation) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVITATION_NOT_FOUND',
                    'message' => 'Invalid invitation token'
                ]
            ], 404);
        }

        if ($invitation->isExpired()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVITATION_EXPIRED',
                    'message' => 'This invitation has expired'
                ]
            ], 410);
        }

        if ($invitation->status !== 'pending') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVITATION_INVALID',
                    'message' => 'This invitation is no longer valid'
                ]
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'invitation' => $invitation->only([
                    'id', 'email', 'name', 'role', 'expires_at'
                ]),
                'store' => $invitation->store->only([
                    'id', 'name', 'email'
                ]),
                'invited_by' => $invitation->invitedBy->only([
                    'id', 'name', 'email'
                ]),
            ],
            'message' => 'Invitation details retrieved successfully'
        ]);
    }

    /**
     * Accept invitation and create user account.
     */
    public function accept(Request $request, string $token): JsonResponse
    {
        $invitation = StaffInvitation::withoutStoreScope()->where('token', $token)->first();

        if (!$invitation) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVITATION_NOT_FOUND',
                    'message' => 'Invalid invitation token'
                ]
            ], 404);
        }

        if ($invitation->isExpired()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVITATION_EXPIRED',
                    'message' => 'This invitation has expired'
                ]
            ], 410);
        }

        if ($invitation->status !== 'pending') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVITATION_INVALID',
                    'message' => 'This invitation is no longer valid'
                ]
            ], 400);
        }

        // Check if user already exists with this email
        if (User::where('email', $invitation->email)->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'USER_EXISTS',
                    'message' => 'A user with this email already exists'
                ]
            ], 409);
        }

        $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
            'name' => 'sometimes|string|max:255', // Allow overriding the invited name
        ]);

        // Create the user account
        $user = User::create([
            'name' => $request->input('name', $invitation->name),
            'email' => $invitation->email,
            'password' => Hash::make($request->password),
            'store_id' => $invitation->store_id,
            'email_verified_at' => now(), // Auto-verify since they accepted invitation
        ]);

        // Assign the role
        $user->assignRole($invitation->role);

        // Mark invitation as accepted
        $invitation->markAsAccepted($user);

        // Log the acceptance activity
        \App\Models\ActivityLog::create([
            'store_id' => $invitation->store_id,
            'user_id' => $user->id,
            'event' => 'staff.invitation.accepted',
            'auditable_type' => StaffInvitation::class,
            'auditable_id' => $invitation->id,
            'new_values' => [
                'user_id' => $user->id,
                'accepted_at' => now()->toISOString(),
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Create authentication token for the new user
        $token = $user->createToken('staff-onboarding')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->load('roles', 'permissions'),
                'token' => $token,
                'store' => $invitation->store->only(['id', 'name', 'email']),
            ],
            'message' => 'Invitation accepted successfully. Welcome to the team!'
        ], 201);
    }

    /**
     * Decline invitation.
     */
    public function decline(string $token): JsonResponse
    {
        $invitation = StaffInvitation::withoutStoreScope()->where('token', $token)->first();

        if (!$invitation) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVITATION_NOT_FOUND',
                    'message' => 'Invalid invitation token'
                ]
            ], 404);
        }

        if ($invitation->status !== 'pending') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVITATION_INVALID',
                    'message' => 'This invitation is no longer valid'
                ]
            ], 400);
        }

        $invitation->markAsCancelled();

        // Log the decline activity
        \App\Models\ActivityLog::create([
            'store_id' => $invitation->store_id,
            'user_id' => null, // No user yet since they declined
            'event' => 'staff.invitation.declined',
            'auditable_type' => StaffInvitation::class,
            'auditable_id' => $invitation->id,
            'old_values' => ['status' => 'pending'],
            'new_values' => ['status' => 'cancelled'],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Invitation declined successfully'
        ]);
    }
}
