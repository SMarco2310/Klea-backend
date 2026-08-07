<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailVerificationController extends Controller
{
    /**
     * Handle the signed verification link click. No frontend redirect is
     * assumed here since this API has no fixed frontend deployment target;
     * it just confirms verification via JSON.
     */
    public function verify(EmailVerificationRequest $request)
    {
        try {
            if ($request->user()->hasVerifiedEmail()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Email already verified',
                ], 200);
            }

            $request->fulfill();

            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error verifying email: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify email',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resend the verification email to the authenticated user.
     */
    public function resend(Request $request)
    {
        try {
            if ($request->user()->hasVerifiedEmail()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Email already verified',
                ], 200);
            }

            $request->user()->sendEmailVerificationNotification();

            return response()->json([
                'success' => true,
                'message' => 'Verification link sent',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error resending verification email: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to resend verification link',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
