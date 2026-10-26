<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    /**
     * Show email verification prompt.
     */
    public function notice(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user && !empty($user->email_verified_at)) {
            return redirect()->route('attendee.dashboard');
        }

        return view('auth.verify-email');
    }

    /**
     * Verify email from signed request.
     */
    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::find($id);

        if (!$user) {
            return redirect()->route('login')->withErrors(['verification' => 'Invalid verification link.']);
        }

        // Validate hash
        if (!hash_equals((string) $hash, sha1($user->email))) {
            return redirect()->route('login')->withErrors(['verification' => 'Verification link signature mismatch.']);
        }

        if (empty($user->email_verified_at)) {
            $user->email_verified_at = now();
            $user->save();
        }

        return redirect()->route('attendee.dashboard')->with('status', 'Your email address has been verified successfully!');
    }

    /**
     * Resend verification notification.
     */
    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user && !empty($user->email_verified_at)) {
            return redirect()->route('attendee.dashboard');
        }

        return back()->with('status', 'A new verification notification has been dispatched to your email address.');
    }
}
