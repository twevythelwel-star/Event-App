@extends('layouts.admin')

@section('title', 'Policies and acknowledgements')
@section('heading', 'Policies and Acknowledgements')
@section('subheading', 'Current versions, drafts and publishing. Publishing a version makes everyone it applies to accept it again.')
@section('breadcrumbs')<span> / <a href="{{ route('admin.console.compliance-centre.dashboard') }}">Compliance dashboard</a></span><span> / Policies</span>@endsection

@section('content')
    @include('admin.compliance-centre._tabs', ['current' => 'policies'])
    @if($errors->any())<div class="ac-alert error" role="alert"><div>{{ $errors->first() }}</div></div>@endif

    <section class="ac-card">
        <div class="ac-card-head"><h2>Current versions</h2></div>
        <div class="ac-table-wrap"><table class="ac-table">
            <thead><tr><th>Policy</th><th>Applies to</th><th>Current version</th><th>People who accepted it</th></tr></thead>
            <tbody>@foreach($current as $name => $spec)
                @php $count = optional($acceptance->get($name))->firstWhere('policy_version', $spec['version'])?->people ?? 0; @endphp
                <tr><td>{{ $spec['title'] }}<div class="ac-hint">{{ $name }}{{ $spec['built_in'] ? '' : ' · added by an administrator' }}</div></td>
                    <td>{{ $spec['group'] === 'host' ? 'Every host' : 'Every user' }}</td>
                    <td>{{ $spec['version'] }}</td>
                    <td>{{ number_format($count) }} of {{ number_format($affected[$spec['group']]) }}</td></tr>
            @endforeach</tbody>
        </table></div>
    </section>

    <section class="ac-card" style="margin-top:16px">
        <div class="ac-card-head"><h2>Versions and drafts</h2></div>
        @if($versions->isEmpty())
            <div class="ac-card-body ac-empty">No versions drafted yet. The built-in text is in force.</div>
        @else
            @foreach($versions as $version)
                <div class="ac-card-body" style="border-top:1px solid var(--ac-border, #233)">
                    <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap">
                        <strong>{{ $version->title }} · version {{ $version->version }}</strong>
                        <span class="ac-badge {{ ['published' => 'success', 'retired' => '', 'draft' => 'warning'][$version->status] }}">{{ \App\Models\Compliance\CompliancePolicyVersion::STATUSES[$version->status] }}</span>
                    </div>
                    <p class="ac-hint" style="margin:4px 0">{{ \App\Models\Compliance\CompliancePolicyVersion::GROUPS[$version->policy_group] }} · drafted by {{ $version->drafter?->name ?? '—' }}
                        @if($version->published_at) · published {{ $version->published_at->format('j M Y') }} by {{ $version->publisher?->name ?? '—' }}@endif</p>
                    <p style="margin:4px 0"><strong>What changed:</strong> {{ $version->change_summary }}</p>
                    <details><summary>Text</summary><p style="white-space:pre-line">{{ $version->body }}</p></details>
                    @if($version->status === 'draft')
                        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-top:8px">
                            @can('publish', $version)
                                <form method="POST" action="{{ route('admin.console.compliance-centre.policies.publish', $version) }}" style="display:flex;gap:6px;align-items:flex-end;flex-wrap:wrap">@csrf
                                    <label style="display:flex;gap:6px;max-width:520px"><input type="checkbox" name="confirm" value="1" required>
                                        <span>{{ $version->policy_group === 'host'
                                            ? 'I understand all ' . number_format($affected['host']) . ' hosts must accept this before they can publish events again.'
                                            : 'I understand every user (' . number_format($affected['account']) . ') must accept this version.' }}</span></label>
                                    <button class="ac-btn ac-btn-primary ac-btn-sm" type="submit">Publish</button>
                                </form>
                            @else
                                <span class="ac-hint">A system administrator publishes drafts.</span>
                            @endcan
                            @can('delete', $version)
                                <form method="POST" action="{{ route('admin.console.compliance-centre.policies.destroy', $version) }}">@csrf @method('DELETE')<button class="ac-btn ac-btn-sm" type="submit">Delete draft</button></form>
                            @endcan
                        </div>
                    @endif
                </div>
            @endforeach
        @endif
    </section>

    @can('create', \App\Models\Compliance\CompliancePolicyVersion::class)
        <section class="ac-card" style="margin-top:16px">
            <div class="ac-card-head"><h2>Draft a new version or a new acknowledgement</h2></div>
            <form method="POST" action="{{ route('admin.console.compliance-centre.policies.store') }}" class="ac-card-body ac-stack">@csrf
                <div class="ac-field"><label for="p-name">Policy</label>
                    <select id="p-name" class="ac-select" name="policy_name">
                        <option value="">A new acknowledgement (named from its title)</option>
                        @foreach($current as $name => $spec)<option value="{{ $name }}" @selected(old('policy_name') === $name)>{{ $spec['title'] }} (now {{ $spec['version'] }})</option>@endforeach
                    </select></div>
                <div class="ac-field"><label for="p-group">Applies to</label>
                    <select id="p-group" class="ac-select" name="policy_group">@foreach(\App\Models\Compliance\CompliancePolicyVersion::GROUPS as $v => $l)<option value="{{ $v }}" @selected(old('policy_group') === $v)>{{ $l }}</option>@endforeach</select></div>
                <div class="ac-field"><label for="p-version">Version</label><input id="p-version" class="ac-input" name="version" value="{{ old('version') }}" placeholder="e.g. 2026.12-v2" required maxlength="40"></div>
                <div class="ac-field"><label for="p-title">Title</label><input id="p-title" class="ac-input" name="title" value="{{ old('title') }}" required maxlength="255"></div>
                <div class="ac-field"><label for="p-body">Text (one statement per line)</label><textarea id="p-body" class="ac-input" name="body" rows="6" required minlength="20">{{ old('body') }}</textarea></div>
                <div class="ac-field"><label for="p-summary">What changed (shown with the draft)</label><input id="p-summary" class="ac-input" name="change_summary" value="{{ old('change_summary') }}" required minlength="10" maxlength="2000"></div>
                <div><button class="ac-btn ac-btn-primary ac-btn-sm" type="submit">Save draft</button></div>
            </form>
        </section>
    @endcan
@endsection
