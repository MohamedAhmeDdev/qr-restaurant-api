<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{

    /**
     * Build the standard user payload.
     * For staff roles, includes assigned restaurants + organization.
     */
    private function buildUserPayload(User $user): array
    {
        $user->load('roles');
        $role = $user->roles->first()?->slug;

        $payload = [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $role,
        ];

        // Staff roles receive assigned restaurant data
        if (! in_array($role, ['super_admin', 'restaurant_admin'])) {
            $assignedRestaurants = $user->assignedRestaurants()
                ->with('organization:id,name,slug')
                ->get([
                    'restaurants.id',
                    'restaurants.organization_id',
                    'restaurants.name',
                    'restaurants.slug',
                ]);

            $firstRestaurant = $assignedRestaurants->first();

            $payload['organization'] = $firstRestaurant?->organization ? [
                'id'   => $firstRestaurant->organization->id,
                'name' => $firstRestaurant->organization->name,
                'slug' => $firstRestaurant->organization->slug,
            ] : null;

            $payload['restaurants'] = $assignedRestaurants->makeHidden('organization');
        }

        return $payload;
    }

    /**
     * Handle User Login (Checks if 2FA is Enabled)
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // 1. Account Deactivation Check
        if (isset($user->is_active) && ! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated.'],
            ]);
        }

        // 2. Restaurant Workspace Check (Non-Admins)
        if (! $user->isSuperAdmin() && ! $user->ownedOrganizations()->exists()) {
            $staffRecords = $user->assignedRestaurants()
                ->whereNull('staff.deleted_at')
                ->get();

            if ($staffRecords->isEmpty()) {
                throw ValidationException::withMessages([
                    'email' => ['Your account is not assigned to any restaurant workspace.'],
                ]);
            }

            $hasActiveRestaurant = $staffRecords->contains(function ($restaurant) {
                return $restaurant->pivot->status === 'active' && $restaurant->is_active && $restaurant->status === 'active';
            });

            if (! $hasActiveRestaurant) {
                throw ValidationException::withMessages([
                    'email' => ['Your staff account is inactive or your assigned restaurant workspace is suspended.'],
                ]);
            }
        }

        // 2FA required
        if ($user->two_factor_enabled) {
            $code = (string) rand(100000, 999999);
            $user->update([
                'two_factor_code' => Hash::make($code),
                'two_factor_expires_at' => now()->addMinutes(10),
            ]);

            $user->notify(new TwoFactorCodeNotification($code));

            return response()->json([
                'two_factor_required' => true,
                'email' => $user->email,
                'message' => 'A two-factor authentication code has been sent to your email.',
            ]);
        }

        $user->tokens()->delete();
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'two_factor_required' => false,
            'message' => 'Login successful',
            'user' => $this->buildUserPayload($user),   // <-- now includes restaurants for staff
            'token' => $token,
        ]);
    }

    /**
     * Toggle Two-Factor Authentication On/Off
     */
    public function toggleTwoFactor(Request $request)
    {
        $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $user = $request->user();
        $user->update([
            'two_factor_enabled' => $request->enabled,
        ]);

        return response()->json([
            'message' => $request->enabled ? 'Two-Factor Authentication enabled.' : 'Two-Factor Authentication disabled.',
            'two_factor_enabled' => $user->two_factor_enabled,
        ]);
    }

    /**
     * Get Current 2FA Status
     */
    public function getTwoFactorStatus(Request $request)
    {
        return response()->json([
            'two_factor_enabled' => (bool) $request->user()->two_factor_enabled,
        ]);
    }

    /**
     * Verify 2FA Code & Complete Login
     */
    public function verifyTwoFactor(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['No account found matching this email address.'],
            ]);
        }

        // 1. Account Deactivation Check
        if (isset($user->is_active) && ! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated.'],
            ]);
        }

        // 2. Restaurant Workspace Check (Non-Admins)
        if (! $user->isSuperAdmin() && ! $user->ownedOrganizations()->exists()) {
            $staffRecords = $user->assignedRestaurants()
                ->whereNull('staff.deleted_at')
                ->get();

            if ($staffRecords->isEmpty()) {
                throw ValidationException::withMessages([
                    'email' => ['Your account is not assigned to any restaurant workspace.'],
                ]);
            }

            $hasActiveRestaurant = $staffRecords->contains(function ($restaurant) {
                return $restaurant->pivot->status === 'active' && $restaurant->is_active && $restaurant->status === 'active';
            });

            if (! $hasActiveRestaurant) {
                throw ValidationException::withMessages([
                    'email' => ['Your staff account is inactive or your assigned restaurant workspace is suspended.'],
                ]);
            }
        }

        if (! $user->two_factor_code || ! $user->two_factor_expires_at || now()->gt($user->two_factor_expires_at)) {
            throw ValidationException::withMessages([
                'code' => ['Your 2FA code has expired. Please log in again to receive a new code.'],
            ]);
        }

        if (! Hash::check($validated['code'], $user->two_factor_code)) {
            throw ValidationException::withMessages([
                'code' => ['The 2FA code you entered is invalid.'],
            ]);
        }

        $user->update([
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
        ]);

        $user->tokens()->delete();
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $this->buildUserPayload($user),   // <-- now includes restaurants for staff
            'token' => $token,
        ]);
    }

    /**
     * Send Password Reset Link Email
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['No account found with this email address.'],
            ]);
        }

        $token = Str::random(60);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        $user->notify(new ResetPasswordNotification($token));

        return response()->json([
            'message' => 'A password reset link has been sent to your email address.',
        ]);
    }

    /**
     * Reset Password Using Token
     */
    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (! $record || ! Hash::check($validated['token'], $record->token)) {
            throw ValidationException::withMessages([
                'token' => ['This password reset token is invalid or has already been used.'],
            ]);
        }

        if (now()->subMinutes(60)->gt($record->created_at)) {
            DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();
            throw ValidationException::withMessages([
                'token' => ['This password reset token has expired. Please request a new one.'],
            ]);
        }

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['No account found with this email address.'],
            ]);
        }

        $user->update(['password' => $validated['password']]);
        $user->tokens()->delete();

        DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

        return response()->json([
            'message' => 'Password has been reset successfully. Please log in with your new password.',
        ]);
    }

    /**
     * Verify Current Auth Token and Return Clean User Data
     */
    public function verify(Request $request)
    {
        return response()->json([
            'user' => $this->buildUserPayload($request->user()),
        ]);
    }

    /**
     * Change authenticated user's password
     */
    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }

    /**
     * Handle User Logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }
}