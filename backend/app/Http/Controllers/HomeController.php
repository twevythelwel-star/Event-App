<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TargetMarket;
use App\Services\Discovery\HomeSections;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The public homepage: multicultural event discovery across the United States, built from real event records.
 * Signed-in users go straight to their own dashboard, as before.
 */
class HomeController extends Controller
{
    public function index(Request $request): View|JsonResponse|RedirectResponse
    {
        if ($user = $request->user()) {
            $activeRole = $user->current_context_role ?? $user->role;

            return redirect()->route(match ($activeRole) {
                'system_admin' => 'system-admin.dashboard',
                'admin' => 'admin.dashboard',
                'event_host' => 'host.dashboard',
                default => 'attendee.dashboard',
            });
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => HomeSections::upcoming()]);
        }

        // The location selector defaults to the whole United States; choosing a market adds "Near <city>".
        $market = $request->filled('market')
            ? TargetMarket::where('is_enabled', true)->find((string) $request->query('market'))
            : null;

        return view('welcome', HomeSections::build($market) + [
            'allMarkets' => TargetMarket::where('is_enabled', true)->orderBy('state_code')->orderBy('city')->get(),
        ]);
    }
}
