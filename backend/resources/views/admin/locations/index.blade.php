@extends('layouts.admin')

@section('title', 'Locations')
@section('heading', 'Location Management')
@section('subheading', 'Manage U.S. states, territories, target markets, and discovery priorities.')
@section('breadcrumbs')<span> / Locations</span>@endsection

@section('content')
    <div class="ac-alert" role="note">Recommended markets are discovery priorities only. They do not indicate confirmed venues, events, hosts, or local partnerships.</div>

    <section class="ac-card" style="margin:18px 0;padding:18px">
        <h2>Add state or territory</h2>
        <form method="POST" action="{{ route('admin.console.locations.states.store') }}" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px">
            @csrf
            <label>Code <input name="code" maxlength="2" required pattern="[A-Z]{2}" placeholder="XX" class="ac-input"></label>
            <label>Name <input name="name" maxlength="80" required class="ac-input"></label>
            <label>Default time zone <select name="default_timezone" required class="ac-input">@foreach($timezones as $timezone)<option value="{{ $timezone }}">{{ $timezone }}</option>@endforeach</select></label>
            <label><input type="checkbox" name="is_state_or_dc" value="1" checked> State or D.C.</label>
            <button class="ac-btn ac-btn-primary" type="submit">Add jurisdiction</button>
        </form>
    </section>

    <section class="ac-card" style="margin:18px 0;padding:18px">
        <h2>Add target market</h2>
        <form method="POST" action="{{ route('admin.console.locations.markets.store') }}" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px">
            @csrf
            <label>City or town <input name="city" maxlength="120" required class="ac-input"></label>
            <label>State/territory <select name="state_code" required class="ac-input">@foreach($states->where('is_enabled', true) as $state)<option value="{{ $state->code }}">{{ $state->name }} ({{ $state->code }})</option>@endforeach</select></label>
            <label>Event-local time zone <select name="default_timezone" required class="ac-input">@foreach($timezones as $timezone)<option value="{{ $timezone }}">{{ $timezone }}</option>@endforeach</select></label>
            <label>ZIP codes (comma-separated) <input name="zip_codes[]" placeholder="33311, 33313" class="ac-input"></label>
            <label>Discovery priority <input name="discovery_priority" type="number" min="1" value="100" required class="ac-input"></label>
            <label><input type="checkbox" name="is_featured" value="1"> Featured city</label>
            <button class="ac-btn ac-btn-primary" type="submit">Add target market</button>
        </form>
    </section>

    <section class="ac-card" style="margin:18px 0;padding:18px">
        <h2>States and territories</h2>
        <div style="overflow:auto">
            <table class="ac-table">
                <thead><tr><th>Jurisdiction</th><th>Code</th><th>Default timezone</th><th>Enabled</th><th>Update</th></tr></thead>
                <tbody>
                @foreach($states as $state)
                    <tr>
                        <td><form id="update-state-{{ $state->code }}" method="POST" action="{{ route('admin.console.locations.states.update', $state) }}">@csrf @method('PATCH')</form><input form="update-state-{{ $state->code }}" name="name" value="{{ $state->name }}" required class="ac-input"></td><td>{{ $state->code }}</td>
                        <td><select form="update-state-{{ $state->code }}" name="default_timezone" class="ac-input">@foreach($timezones as $timezone)<option value="{{ $timezone }}" @selected($state->default_timezone === $timezone)>{{ $timezone }}</option>@endforeach</select></td>
                        <td><input form="update-state-{{ $state->code }}" type="hidden" name="is_enabled" value="0"><input form="update-state-{{ $state->code }}" type="checkbox" name="is_enabled" value="1" @checked($state->is_enabled)></td>
                        <td><button form="update-state-{{ $state->code }}" class="ac-btn ac-btn-sm" type="submit">Save</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="ac-card" style="margin:18px 0;padding:18px">
        <h2>Target markets</h2>
        <div style="overflow:auto">
            <table class="ac-table">
                <thead><tr><th>City</th><th>State</th><th>ZIP codes</th><th>Time zone</th><th>Priority</th><th>Featured / Enabled</th><th>Published events</th><th>Hosts on published events</th><th>Update</th></tr></thead>
                <tbody>
                @foreach($markets as $market)
                    <tr>
                        <td><form id="update-market-{{ $market->id }}" method="POST" action="{{ route('admin.console.locations.markets.update', $market) }}">@csrf @method('PATCH')</form><input form="update-market-{{ $market->id }}" name="city" value="{{ $market->city }}" required class="ac-input"></td>
                        <td><select form="update-market-{{ $market->id }}" name="state_code" class="ac-input">@foreach($states->where('is_enabled', true) as $state)<option value="{{ $state->code }}" @selected($market->state_code === $state->code)>{{ $state->code }}</option>@endforeach</select></td>
                        <td><input form="update-market-{{ $market->id }}" name="zip_codes[]" value="{{ implode(', ', $market->zip_codes ?? []) }}" class="ac-input" aria-label="ZIP codes (replace comma-separated values)"></td>
                        <td><select form="update-market-{{ $market->id }}" name="default_timezone" class="ac-input">@foreach($timezones as $timezone)<option value="{{ $timezone }}" @selected($market->default_timezone === $timezone)>{{ $timezone }}</option>@endforeach</select></td>
                        <td><input form="update-market-{{ $market->id }}" name="discovery_priority" type="number" min="1" value="{{ $market->discovery_priority }}" class="ac-input"></td>
                        <td><label><input form="update-market-{{ $market->id }}" type="checkbox" name="is_featured" value="1" @checked($market->is_featured)> Featured</label><br><label><input form="update-market-{{ $market->id }}" type="hidden" name="is_enabled" value="0"><input form="update-market-{{ $market->id }}" type="checkbox" name="is_enabled" value="1" @checked($market->is_enabled)> Enabled</label></td>
                        <td>{{ $market->published_events_count }}</td><td>{{ $marketHostCounts[$market->id] ?? 0 }}</td>
                        <td><button form="update-market-{{ $market->id }}" class="ac-btn ac-btn-sm" type="submit">Save</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $markets->links() }}
    </section>
@endsection
