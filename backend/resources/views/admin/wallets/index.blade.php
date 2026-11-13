@extends('layouts.admin')

@section('title', 'Wallet Passes')
@section('breadcrumbs')<span aria-hidden="true">›</span><span aria-current="page">Wallet Passes</span>@endsection
@section('heading', 'Wallet Passes')
@section('subheading', 'Apple, Google and Samsung Wallet: configuration, approval, pass operations and provider errors. Credentials live in the server environment and are never shown here.')

@section('content')
    @foreach($warnings as $warning)
        <div class="ac-alert {{ $warning['level'] === 'error' ? 'error' : 'warning' }}" role="alert" data-wallet-warning>
            <x-icon name="alert" size="sm" />
            <span><strong>{{ $warning['provider'] }}:</strong> {{ $warning['message'] }}</span>
        </div>
    @endforeach

    <div class="ac-grid ac-grid-2">
        @foreach($providers as $key => $provider)
            <div class="ac-card" data-wallet-provider="{{ $key }}">
                <div class="ac-card-head">
                    <h2 style="margin:0;">{{ $provider['label'] }}</h2>
                    @if($provider['live'])
                        <span class="ac-badge success">Shown to customers</span>
                    @elseif($provider['configured'])
                        <span class="ac-badge warning">Configured, not live</span>
                    @elseif($provider['enabled'])
                        <span class="ac-badge danger">Enabled, not configured</span>
                    @else
                        <span class="ac-badge">Off</span>
                    @endif
                </div>
                <div class="ac-card-body">
                    <dl class="ac-dl">
                        @foreach($provider['details'] as $label => $value)
                            <dt>{{ $label }}</dt><dd>{{ $value }}</dd>
                        @endforeach
                        <dt>Last successful sync</dt>
                        <dd>{{ $totals[$key]['last_sync']?->format('j M Y H:i') ?? 'never' }}</dd>
                    </dl>
                    @foreach(array_merge($provider['problems'], $provider['pending'] ?? []) as $problem)
                        <p class="ac-hint" style="margin:.25rem 0;">{{ $problem }}</p>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="ac-card">
        <div class="ac-card-head"><h2 style="margin:0;">Pass totals</h2></div>
        <div class="ac-table-wrap">
            <table class="ac-table">
                <thead><tr><th>Provider</th><th style="text-align:right">Passes</th><th style="text-align:right">Saved</th><th style="text-align:right">Revoked</th><th style="text-align:right">Successful operations</th><th style="text-align:right">Failed operations</th><th style="text-align:right">Pending</th></tr></thead>
                <tbody>
                    @foreach($totals as $key => $row)
                        <tr>
                            <td>{{ $providers[$key]['label'] }}</td>
                            <td style="text-align:right">{{ $row['passes'] }}</td>
                            <td style="text-align:right">{{ $row['saved'] }}</td>
                            <td style="text-align:right">{{ $row['revoked'] }}</td>
                            <td style="text-align:right" data-total="successful-{{ $key }}">{{ $row['successful'] }}</td>
                            <td style="text-align:right" data-total="failed-{{ $key }}">{{ $row['failed'] }}</td>
                            <td style="text-align:right" data-total="pending-{{ $key }}">{{ $row['pending'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="ac-hint">Pending: waiting for the provider to confirm the holder saved the pass, or being retried after an error. A pass counts as saved only on the provider's confirmation (or, for Apple, a validated pass file delivered).</p>
    </div>

    <div class="ac-card">
        <div class="ac-card-head"><h2 style="margin:0;">Provider errors — last 7 days</h2></div>
        @if($providerErrors->isEmpty())
            <div class="ac-empty"><strong>No provider errors in the last 7 days</strong></div>
        @else
            <div class="ac-table-wrap">
                <table class="ac-table">
                    <thead><tr><th>Provider</th><th>Operation</th><th>Error</th><th style="text-align:right">Count</th><th>Last seen</th></tr></thead>
                    <tbody>
                        @foreach($providerErrors as $row)
                            <tr>
                                <td>{{ $providers[$row->provider]['label'] ?? $row->provider }}</td>
                                <td>{{ $row->action }}</td>
                                <td><code>{{ $row->error_code }}</code></td>
                                <td style="text-align:right">{{ $row->total }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($row->last_at)->format('j M H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="ac-card">
        <div class="ac-card-head"><h2 style="margin:0;">Pass update history</h2></div>
        @if($history->isEmpty())
            <div class="ac-empty"><strong>No wallet operations yet</strong></div>
        @else
            <div class="ac-table-wrap">
                <table class="ac-table">
                    <thead><tr><th>When</th><th>Provider</th><th>Pass</th><th>Operation</th><th>Outcome</th><th>Error</th></tr></thead>
                    <tbody>
                        @foreach($history as $event)
                            <tr>
                                <td>{{ $event->created_at?->format('j M H:i:s') }}</td>
                                <td>{{ $providers[$event->provider]['label'] ?? $event->provider }}</td>
                                <td><code>{{ \Illuminate\Support\Str::limit((string) $event->wallet_pass_id, 8, '…') }}</code></td>
                                <td>{{ $event->action }}</td>
                                <td><span class="ac-badge {{ $event->outcome === 'success' ? 'success' : 'danger' }}">{{ $event->outcome }}</span></td>
                                <td>@if($event->error_code)<code>{{ $event->error_code }}</code>@else — @endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
