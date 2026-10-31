@extends('layouts.admin')

@section('title', 'Dashboard')
@section('breadcrumbs')<span aria-hidden="true">›</span><span aria-current="page">Dashboard</span>@endsection
@section('heading', 'Dashboard')
@section('subheading')
    Platform activity you have access to. "Recent" figures cover the last {{ $periodDays }} days, up to now.
@endsection

@section('content')
    @if(!$hasAnyCapability)
        <div class="ac-card">
            <div class="ac-empty">
                <strong>No administration areas are switched on for your account yet</strong>
                A System Administrator assigns your permissions and organizers under Roles &amp; Permissions.
            </div>
        </div>
    @else
        @if($metrics !== [])
            <div class="ac-metrics">
                @foreach($metrics as $metric)
                    <div class="ac-card ac-metric">
                        @if(!empty($metric['href']))<a href="{{ $metric['href'] }}">@endif
                        <div class="ac-metric-label">{{ $metric['label'] }}</div>
                        <div class="ac-metric-value">{{ $metric['value'] }}</div>
                        <div class="ac-metric-note">{{ $metric['note'] }}</div>
                        @if(!empty($metric['href']))</a>@endif
                        <p class="ac-definition">{{ $metric['definition'] }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="ac-grid ac-grid-2">
            @if($revenue->isNotEmpty() || Gate::allows(\App\Support\AdminAuthorization::PAYMENTS_VIEW))
                <section class="ac-card" aria-labelledby="collected-heading">
                    <div class="ac-card-head"><h2 id="collected-heading">Payments verified · last {{ $periodDays }} days</h2></div>
                    <div class="ac-card-body">
                        @forelse($revenue as $currency => $amount)
                            <div class="ac-metric-value" style="font-size:22px">{{ \App\Support\Money::formatMinor((int) $amount, $currency) }} <span style="font-size:13px;font-weight:600">{{ $currency }}</span></div>
                        @empty
                            <p style="margin:0;color:var(--ac-text-2)">No verified payments in this period.</p>
                        @endforelse
                        <p class="ac-definition">Sum of amount paid on orders placed in the period with status paid or partially paid. Shown per currency; currencies are never added together.</p>
                    </div>
                </section>
            @endif

            @if($canSeeAudit)
                <section class="ac-card" aria-labelledby="activity-heading">
                    <div class="ac-card-head">
                        <h2 id="activity-heading">Recent administrative activity</h2>
                        <a class="ac-link" href="{{ route('admin.console.audit.index') }}">View audit log</a>
                    </div>
                    @if($recentActivity->isEmpty())
                        <div class="ac-empty"><strong>No activity recorded yet</strong>Actions such as payment decisions and role changes appear here.</div>
                    @else
                        <div class="ac-table-wrap">
                            <table class="ac-table">
                                <thead><tr><th scope="col">When</th><th scope="col">Who</th><th scope="col">Action</th></tr></thead>
                                <tbody>
                                @foreach($recentActivity as $log)
                                    <tr>
                                        <td><time datetime="{{ $log->created_at?->toIso8601String() }}">{{ $log->created_at?->diffForHumans() }}</time></td>
                                        <td>{{ $log->user?->name ?? 'System' }}</td>
                                        <td>{{ str_replace('_', ' ', $log->action) }} <span class="ac-cell-sub">{{ $log->target_entity }}</span></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @endif
        </div>
    @endif
@endsection
