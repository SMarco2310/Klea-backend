<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    /**
     * Send a password reset link to the given email, if an account exists.
     * Always responds the same way regardless of whether the email is
     * registered, to avoid leaking which emails have accounts.
     */
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        try {
            Password::sendResetLink($request->only('email'));

            return response()->json([
                'success' => true,
                'message' => 'If an account exists for that email, a password reset link has been sent.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error sending password reset link: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to send password reset link',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset a user's password given a valid token from the emailed link.
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        try {
            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function (User $user, string $password) {
                    $user->forceFill([
                        'password' => Hash::make($password),
                        'remember_token' => Str::random(60),
                    ])->save();

                    // Revoke existing tokens so a leaked/stale session
                    // can't survive a password reset.
                    $user->tokens()->delete();
                }
            );

            if ($status !== Password::PASSWORD_RESET) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to reset password',
                    'error' => __($status),
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error resetting password: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
