<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TargetMarket;
use App\Models\UsState;
use Illuminate\Http\JsonResponse;

class LocationCatalogController extends Controller
{
    public function index(): JsonResponse
    {
        $states = UsState::where('is_enabled', true)->orderBy('name')->get(['code', 'name', 'default_timezone', 'is_state_or_dc']);
        $markets = TargetMarket::with('state:code,name')
            ->where('is_enabled', true)
            ->orderByDesc('is_featured')
            ->orderBy('discovery_priority')
            ->get()
            ->map(fn (TargetMarket $market) => [
                'id' => $market->id,
                'city' => $market->city,
                'stateCode' => $market->state_code,
                'stateName' => $market->state?->name,
                'timezone' => $market->default_timezone,
                'zipCodes' => $market->zip_codes ?? [],
                'featured' => $market->is_featured,
                'priority' => $market->discovery_priority,
                'publishedEvents' => $market->events()->where('status', 'published')->count(),
                'participatingHosts' => $market->events()->where('status', 'published')->distinct()->count('organizer_id'),
            ]);

        return response()->json(['country' => 'United States', 'countryCode' => 'US', 'states' => $states, 'markets' => $markets]);
    }
}
