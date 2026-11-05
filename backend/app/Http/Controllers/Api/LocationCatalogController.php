<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TargetMarket;
use App\Models\UsState;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LocationCatalogController extends Controller
{
    public function index(): JsonResponse
    {
        $states = UsState::where('is_enabled', true)->orderBy('name')->get(['code', 'name', 'default_timezone', 'is_state_or_dc']);
        $eventStats = DB::table('events')->select('target_market_id')
            ->selectRaw('COUNT(*) as published_events, COUNT(DISTINCT organizer_id) as participating_hosts')
            ->where('status', 'published')->whereNotNull('target_market_id')->groupBy('target_market_id')->get()
            ->keyBy('target_market_id');

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
                'publishedEvents' => (int) ($eventStats[$market->id]->published_events ?? 0),
                'participatingHosts' => (int) ($eventStats[$market->id]->participating_hosts ?? 0),
            ]);

        return response()->json(['country' => 'United States', 'countryCode' => 'US', 'states' => $states, 'markets' => $markets]);
    }
}
