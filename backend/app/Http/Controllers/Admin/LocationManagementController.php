<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TargetMarket;
use App\Models\UsState;
use App\Services\LocationAddressNormalizer;
use App\Support\AdminAuthorization as Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LocationManagementController extends Controller
{
    public function index(): View
    {
        Gate::authorize(Admin::SETTINGS_MANAGE);

        $markets = TargetMarket::with('state')->withCount([
            'events as published_events_count' => fn ($query) => $query->where('status', 'published'),
        ])->orderBy('discovery_priority')->orderBy('city')->paginate(30);
        $marketHostCounts = TargetMarket::query()
            ->select('target_markets.id')
            ->selectRaw('COUNT(DISTINCT events.organizer_id) as participating_hosts_count')
            ->leftJoin('events', function ($join) {
                $join->on('events.target_market_id', '=', 'target_markets.id')->where('events.status', '=', 'published');
            })
            ->groupBy('target_markets.id')
            ->pluck('participating_hosts_count', 'id');

        return view('admin.locations.index', [
            'markets' => $markets,
            'marketHostCounts' => $marketHostCounts,
            'states' => UsState::orderBy('name')->get(),
            'timezones' => $this->timezones(),
        ]);
    }

    public function storeState(Request $request): RedirectResponse
    {
        Gate::authorize(Admin::SETTINGS_MANAGE);
        $data = $request->validate([
            'code' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/', 'unique:us_states,code'],
            'name' => ['required', 'string', 'max:80', 'unique:us_states,name'],
            'default_timezone' => ['required', Rule::in(LocationAddressNormalizer::supportedTimezones())],
            'is_state_or_dc' => ['nullable', 'boolean'],
        ]);
        $data['is_state_or_dc'] = $request->boolean('is_state_or_dc');
        $data['is_enabled'] = true;
        UsState::create($data);

        return back()->with('success', 'State or territory added.');
    }

    public function updateState(Request $request, UsState $state): RedirectResponse
    {
        Gate::authorize(Admin::SETTINGS_MANAGE);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('us_states', 'name')->ignore($state->code, 'code')],
            'default_timezone' => ['required', Rule::in(LocationAddressNormalizer::supportedTimezones())],
            'is_enabled' => ['required', 'boolean'],
        ]);
        if (!$request->boolean('is_enabled') && $state->markets()->where('is_enabled', true)->exists()) {
            return back()->withErrors(['is_enabled' => 'Disable this state’s target markets before disabling the state.']);
        }
        $state->update($data);

        return back()->with('success', 'State or territory updated.');
    }

    public function storeMarket(Request $request): RedirectResponse
    {
        Gate::authorize(Admin::SETTINGS_MANAGE);
        $data = $this->validatedMarket($request);
        $data['slug'] = Str::slug($data['city'] . '-' . $data['state_code']);
        $data['metadata'] = ['designation' => 'recommended_target_market'];
        TargetMarket::create($data);

        return back()->with('success', 'Target market added. This designation does not imply a venue, event, or local partnership.');
    }

    public function updateMarket(Request $request, TargetMarket $market): RedirectResponse
    {
        Gate::authorize(Admin::SETTINGS_MANAGE);
        $data = $this->validatedMarket($request, $market);
        $data['slug'] = Str::slug($data['city'] . '-' . $data['state_code']);
        $market->update($data);

        return back()->with('success', 'Target market updated.');
    }

    private function validatedMarket(Request $request, ?TargetMarket $market = null): array
    {
        $zipCodes = $request->input('zip_codes', []);
        $zipCodes = is_array($zipCodes) ? $zipCodes : [$zipCodes];
        $zipCodes = array_values(array_filter(array_map(
            fn ($zip) => trim((string) $zip),
            array_merge(...array_map(fn ($entry) => explode(',', (string) $entry), $zipCodes)),
        )));
        $request->merge(['zip_codes' => $zipCodes]);

        $data = $request->validate([
            'city' => ['required', 'string', 'max:120'],
            'state_code' => ['required', 'string', Rule::exists('us_states', 'code')->where('is_enabled', true)],
            'default_timezone' => ['required', Rule::in(LocationAddressNormalizer::supportedTimezones())],
            'zip_codes' => ['nullable', 'array', 'max:50'],
            'zip_codes.*' => ['required', 'regex:/^\d{5}(?:-\d{4})?$/'],
            'discovery_priority' => ['required', 'integer', 'min:1', 'max:65535'],
            'is_featured' => ['nullable', 'boolean'],
            'is_enabled' => [$market ? 'required' : 'nullable', 'boolean'],
        ]);
        $data['city'] = mb_convert_case(trim($data['city']), MB_CASE_TITLE, 'UTF-8');
        $duplicate = TargetMarket::where('state_code', $data['state_code'])
            ->whereRaw('LOWER(city) = ?', [mb_strtolower($data['city'])])
            ->when($market, fn ($query) => $query->where('id', '!=', $market->id))
            ->exists();
        if ($duplicate) {
            throw \Illuminate\Validation\ValidationException::withMessages(['city' => 'This city already exists as a target market in that state.']);
        }
        $slug = Str::slug($data['city'] . '-' . $data['state_code']);
        if (TargetMarket::where('slug', $slug)->when($market, fn ($query) => $query->where('id', '!=', $market->id))->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['city' => 'This city would duplicate another target market identifier.']);
        }

        return array_merge($data, [
            'is_featured' => $request->boolean('is_featured'),
            'is_enabled' => $request->boolean('is_enabled', true),
        ]);
    }

    /** @return array<int, string> */
    private function timezones(): array
    {
        return LocationAddressNormalizer::supportedTimezones();
    }
}
