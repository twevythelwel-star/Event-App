{{-- Tabs for the compliance dashboard, and the jurisdiction (state) filter shared by the queues that use it. --}}
@php
    $tabs = array_filter([
        'dashboard' => 'Overview',
        'identity' => $canIdentity ? 'Identity' : null,
        'permits' => 'Food permits',
        'licences' => 'Alcohol licences',
        'declarations' => 'Declarations',
        'expiring' => 'Expiring',
        'safety' => 'Safety reports',
        'hosts' => 'Hosts',
        'policies' => 'Policies',
        'records' => $canRecords ? 'Consent & audit' : null,
        'access' => 'Access',
    ]);
    $current = $current ?? 'dashboard';
    $urlFor = fn ($tab) => match ($tab) {
        'permits' => route('admin.console.compliance.evidence.index', ['kind' => 'food']),
        'licences' => route('admin.console.compliance.evidence.index', ['kind' => 'alcohol']),
        default => route('admin.console.compliance-centre.' . $tab, in_array($tab, ['dashboard', 'declarations', 'expiring', 'safety'], true) && $stateFilter ? ['state' => $stateFilter] : []),
    };
@endphp
<nav aria-label="Compliance dashboard sections" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px">
    @foreach($tabs as $tab => $label)
        <a class="ac-btn ac-btn-sm {{ $current === $tab ? 'ac-btn-primary' : '' }}" href="{{ $urlFor($tab) }}" @if($current === $tab) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
@if(!empty($withStateFilter))
    <form method="GET" class="ac-filters" style="margin-bottom:12px">
        @foreach(request()->except(['state', 'page']) as $key => $value)
            @if(is_string($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
        @endforeach
        <div class="ac-field"><label for="cc-state">Jurisdiction (state)</label>
            <select id="cc-state" class="ac-select" name="state" onchange="this.form.submit()">
                <option value="">All states</option>
                @foreach($states as $s)<option value="{{ $s->code }}" @selected($stateFilter === $s->code)>{{ $s->name }}</option>@endforeach
            </select></div>
        <noscript><button class="ac-btn ac-btn-sm" type="submit">Filter</button></noscript>
    </form>
@endif
