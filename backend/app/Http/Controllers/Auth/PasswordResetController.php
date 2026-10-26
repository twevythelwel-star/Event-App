<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Services\AuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    /**
     * Show request password reset form.
     */
    public function showForgotForm(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Send password reset link token.
     */
    public function sendResetLink(ForgotPasswordRequest $request): RedirectResponse
    {
        $email = $request->validated()['email'];

        // Generate token and record in database
        AuthenticationService::createPasswordResetToken($email);

        // Standard security response: generic message avoiding account enumeration
        return back()->with('status', 'If an account exists for that email, a password reset link has been dispatched.');
    }

    /**
     * Show reset password form.
     */
    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    /**
     * Handle password reset submission.
     */
    public function resetPassword(ResetPasswordRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $result = AuthenticationService::resetPassword(
            $validated['email'],
            $validated['token'],
            $validated['password']
        );

        if (!$result['success']) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['reset' => $result['error']]);
        }

        return redirect()->route('login')->with('status', $result['message']);
    }
}
