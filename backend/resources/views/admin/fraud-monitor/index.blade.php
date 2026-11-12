@extends('layouts.admin')

@section('title', 'Fraud Monitor')
@section('breadcrumbs')<span aria-hidden="true">›</span><span aria-current="page">Fraud Monitor</span>@endsection
@section('heading', 'Scanner &amp; Gate Fraud Monitor')
@section('subheading', 'Signals from the append-only security audit trail. Counts are context for investigation — a repeated scan is not proof of fraud.')

@section('content')
    @if($suspiciousActors->isNotEmpty())
        <div class="ac-alert error" role="alert">
            <x-icon name="alert" size="sm" />
            <span><strong>{{ $suspiciousActors->count() }} staff account{{ $suspiciousActors->count() === 1 ? '' : 's' }}</strong> with 5+ rejections in the last 24 hours. Review the recent rejections below before acting.</span>
        </div>
    @endif

    <div class="ac-card">
        <h2 style="margin-top:0;">Rejections by staff, event and IP — last 24 hours</h2>
        @if($actors24h->isEmpty())
            <div class="ac-empty"><strong>No scanner or handover rejections in the last 24 hours</strong></div>
        @else
            <table class="ac-table">
                <thead><tr><th>Staff</th><th>IP</th><th>Event</th><th style="text-align:right">Rejections</th></tr></thead>
                <tbody>
                    @foreach($actors24h as $row)
                        <tr>
                            <td>{{ $row->user_id ?? 'system' }}</td>
                            <td><code>{{ $row->ip_address }}</code></td>
                            <td>{{ \Illuminate\Support\Str::limit((string) $row->event_id, 13) }}</td>
                            <td style="text-align:right"><strong>{{ $row->rejections }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="ac-card">
        <h2 style="margin-top:0;">Signal mix — last 7 days</h2>
        @if($actionMix->isEmpty())
            <div class="ac-empty"><strong>No security events recorded yet</strong></div>
        @else
            <table class="ac-table">
                <thead><tr><th>Action</th><th>Outcome</th><th style="text-align:right">Count</th></tr></thead>
                <tbody>
                    @foreach($actionMix as $row)
                        <tr>
                            <td>{{ str_replace('_', ' ', $row->action) }}</td>
                            <td>{{ $row->outcome }}</td>
                            <td style="text-align:right">{{ $row->total }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="ac-card">
        <h2 style="margin-top:0;">Recent rejections — last 7 days</h2>
        @if($recentRejections->isEmpty())
            <div class="ac-empty"><strong>No rejections recorded</strong></div>
        @else
            <table class="ac-table">
                <thead><tr><th>When</th><th>Staff</th><th>Action</th><th>Event</th><th>Reason / detail</th></tr></thead>
                <tbody>
                    @foreach($recentRejections as $log)
                        <tr>
                            <td>{{ $log->created_at?->format('M j, H:i') }}</td>
                            <td>{{ $log->user?->name ?? $log->user?->email ?? 'system' }}</td>
                            <td>{{ str_replace('_', ' ', $log->action) }}</td>
                            <td>{{ \Illuminate\Support\Str::limit((string) $log->event_id, 13) }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($log->details, 120) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
