<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\SecurityException;
use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\OrganizerUser;
use App\Models\User;
use App\Services\AuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

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

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'avatar_url' => ['nullable', 'string', 'max:512'],
            'bio' => ['nullable', 'string', 'max:500'],
            'marketing_preferences' => ['nullable', 'array'],
        ]);

        if (isset($validated['name'])) {
            $user->name = trim($validated['name']);
        }
        if (isset($validated['email'])) {
            $user->email = strtolower(trim($validated['email']));
        }
        if (array_key_exists('phone', $validated)) {
            $user->phone = $validated['phone'];
        }
        if (array_key_exists('avatar_url', $validated)) {
            $user->avatar_url = $validated['avatar_url'];
        }
        if (array_key_exists('bio', $validated)) {
            $user->bio = $validated['bio'];
        }
        if (array_key_exists('marketing_preferences', $validated)) {
            $user->marketing_preferences = $validated['marketing_preferences'];
        }

        $user->save();

        return response()->json([
            'user' => $this->serializeUser($user),
            'message' => 'Profile updated successfully.'
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'error' => 'Current password does not match records.'
            ], 422);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully.'
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $userEmail = strtolower(trim($user->email));
        $userPhone = preg_replace('/[^\d]/', '', $user->phone ?? '');

        $logs = NotificationLog::where(function ($q) use ($userEmail, $userPhone, $user) {
            $q->where('recipient', $userEmail);
            if ($userPhone) {
                $q->orWhere('recipient', $userPhone);
            }
            $q->orWhereIn('order_id', Order::where('user_id', $user->id)->pluck('id'));
        })
        ->orderBy('created_at', 'desc')
        ->take(50)
        ->get();

        $unreadCount = $logs->whereNull('read_at')->count();

        return response()->json([
            'data' => $logs,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markNotificationRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $userEmail = strtolower(trim($user->email));
        $userPhone = preg_replace('/[^\d]/', '', $user->phone ?? '');

        $log = NotificationLog::where('id', $id)
            ->where(function ($q) use ($userEmail, $userPhone, $user) {
                $q->where('recipient', $userEmail);
                if ($userPhone) {
                    $q->orWhere('recipient', $userPhone);
                }
                $q->orWhereIn('order_id', Order::where('user_id', $user->id)->pluck('id'));
            })
            ->first();

        if ($log && !$log->read_at) {
            $log->update(['read_at' => now()]);
        }

        return response()->json(['success' => true]);
    }

    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $userEmail = strtolower(trim($user->email));
        $userPhone = preg_replace('/[^\d]/', '', $user->phone ?? '');

        NotificationLog::where(function ($q) use ($userEmail, $userPhone, $user) {
            $q->where('recipient', $userEmail);
            if ($userPhone) {
                $q->orWhere('recipient', $userPhone);
            }
            $q->orWhereIn('order_id', Order::where('user_id', $user->id)->pluck('id'));
        })
        ->whereNull('read_at')
        ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    private function serializeUser(?User $user): ?array
    {
        if (!$user) {
            return null;
        }

        $ordersCount = Order::where('user_id', $user->id)->count();
        $paidCount = Order::where('user_id', $user->id)->where('status', 'paid')->count();
        $membershipStatus = $paidCount > 0 ? 'Verified Passholder' : ($user->email_verified_at ? 'Verified Member' : 'Standard Member');

        $userEmail = strtolower(trim($user->email));
        $userPhone = preg_replace('/[^\d]/', '', $user->phone ?? '');

        $unreadCount = NotificationLog::where(function ($q) use ($userEmail, $userPhone, $user) {
            $q->where('recipient', $userEmail);
            if ($userPhone) {
                $q->orWhere('recipient', $userPhone);
            }
            $q->orWhereIn('order_id', Order::where('user_id', $user->id)->pluck('id'));
        })->whereNull('read_at')->count();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_url,
            'bio' => $user->bio,
            'marketing_preferences' => $user->marketing_preferences,
            'membership_status' => $membershipStatus,
            'member_since' => $user->created_at ? $user->created_at->format('M Y') : 'Oct 2026',
            'orders_count' => $ordersCount,
            'unread_notifications_count' => $unreadCount,
            'roles' => $user->getAuthorizedRoles(),
            'activeRole' => $user->getActiveRole()->value,
            // Organizers the user is an active member of (hosts, verifiers, collectors)
            'organizerIds' => OrganizerUser::where('user_id', $user->id)->where('is_active', true)->pluck('organizer_id')->values(),
        ];
    }
}
