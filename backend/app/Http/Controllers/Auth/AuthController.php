<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Exceptions\SecurityException;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Services\AuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Show registration form.
     */
    public function showRegisterForm(): View
    {
        return view('auth.register');
    }

    /**
     * Handle incoming user registration.
     */
    public function register(RegisterRequest $request): RedirectResponse
    {
        try {
            $result = AuthenticationService::register($request->validated());

            // Authenticate user session
            if (function_exists('auth') && auth()->guard()) {
                auth()->login($result['user']);
                $request->session()->regenerate();
            }

            return redirect()
                ->route($result['redirect_route'])
                ->with('status', $result['message']);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['registration' => $e->getMessage()]);
        } catch (SecurityException $e) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['security' => 'Registration violation: ' . $e->getMessage()]);
        }
    }

    /**
     * Show sign-in form.
     */
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    /**
     * Handle incoming sign-in request.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();
        $remember = !empty($credentials['remember']);

        $result = AuthenticationService::login(
            $credentials['email'],
            $credentials['password'],
            $remember
        );

        if (!$result['success']) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => $result['error']]);
        }

        // Authenticate user session in Laravel guard
        if (function_exists('auth') && auth()->guard()) {
            auth()->login($result['user'], $remember);
            $request->session()->regenerate();
        }

        $intendedUrl = $request->session()->get('url.intended');
        $defaultRoute = route($result['redirect_route']);

        // Preserve intended destinations only when the user is authorized to access them
        if ($intendedUrl) {
            $user = $result['user'];
            $isAuthorizedForIntended = true;

            if (str_contains($intendedUrl, '/host/') && !$user->hasRole(\App\Enums\AccountType::EVENT_HOST) && !$user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN)) {
                $isAuthorizedForIntended = false;
            } elseif (str_contains($intendedUrl, '/admin/') && !$user->hasRole(\App\Enums\AccountType::ADMINISTRATOR) && !$user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN)) {
                $isAuthorizedForIntended = false;
            } elseif (str_contains($intendedUrl, '/system-admin/') && !$user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN)) {
                $isAuthorizedForIntended = false;
            }

            if (!$isAuthorizedForIntended) {
                $request->session()->forget('url.intended');
                return redirect()->to($defaultRoute);
            }
        }

        return redirect()->intended($defaultRoute);
    }

    /**
     * Secure sign-out with session invalidation.
     */
    public function logout(Request $request): RedirectResponse
    {
        if (function_exists('auth') && auth()->guard()) {
            auth()->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been signed out successfully.');
    }
}
