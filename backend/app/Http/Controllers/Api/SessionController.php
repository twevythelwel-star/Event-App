<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\SecurityException;
use App\Http\Controllers\Controller;
use App\Models\OrganizerUser;
use App\Models\User;
use App\Services\AuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Cookie-session authentication for the single-page app (served same-origin through the Vite proxy).
 */
class SessionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->serializeUser($request->user())]);
    }

    public function store(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $result = AuthenticationService::login($credentials['email'], $credentials['password']);

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], 422);
        }

        Auth::login($result['user']);
        $request->session()->regenerate();

        return response()->json(['user' => $this->serializeUser($result['user'])]);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'agree_terms' => ['accepted'],
        ]);

        try {
            $result = AuthenticationService::register($data);
        } catch (\InvalidArgumentException|SecurityException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $user = $result['user'] ?? User::where('email', strtolower(trim($data['email'])))->firstOrFail();

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['user' => $this->serializeUser($user)], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['user' => null]);
    }

    private function serializeUser(?User $user): ?array
    {
        if (!$user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'roles' => $user->getAuthorizedRoles(),
            'activeRole' => $user->getActiveRole()->value,
            // Organizers the user is an active member of (hosts, verifiers, collectors)
            'organizerIds' => OrganizerUser::where('user_id', $user->id)->where('is_active', true)->pluck('organizer_id')->values(),
        ];
    }
}
