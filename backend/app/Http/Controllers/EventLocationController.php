<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Services\Events\MapLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Looks up a venue address with the configured mapping service for the event form. Each result carries a signed
 * token; the form sends the chosen one back and only then is a pin saved (see EventDetails).
 */
class EventLocationController extends Controller
{
    public function lookup(Request $request, MapLocationService $maps): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && ($user->hasRole(AccountType::EVENT_HOST) || $user->hasRole(AccountType::SYSTEM_ADMIN)), 403);

        $data = $request->validate([
            'address' => ['required', 'string', 'min:5', 'max:300'],
            'country_code' => ['nullable', 'string', 'size:2'],
        ]);
        if (!$maps->enabled()) {
            return response()->json(['enabled' => false, 'results' => [],
                'message' => 'Map lookup is not set up for this site. Check the address carefully; no map pin will be shown.']);
        }

        $results = $maps->lookup($data['address'], strtoupper($data['country_code'] ?? 'US'));

        return response()->json([
            'enabled' => true,
            'results' => array_map(fn ($r) => $r + ['preview' => $maps->previewUrl($r['latitude'], $r['longitude'], 480, 240)], $results),
            'message' => $results === [] ? 'No matching place was found. Check the address, or save without a map pin.' : null,
        ]);
    }
}
