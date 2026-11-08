@extends('layouts.admin')

@section('title', 'Compliance access')
@section('heading', 'Compliance Access')
@section('subheading', 'Who may manage compliance, and who may open host identity documents.')
@section('breadcrumbs')<span> / <a href="{{ route('admin.console.compliance-centre.dashboard') }}">Compliance dashboard</a></span><span> / Access</span>@endsection

@section('content')
    @include('admin.compliance-centre._tabs', ['current' => 'access'])
    @if($errors->any())<div class="ac-alert error" role="alert"><div>{{ $errors->first() }}</div></div>@endif
    <div class="ac-alert info" role="note"><div>
        <strong>Identity documents are opened only by administrators granted access here, whatever their role.</strong>
        Being a system administrator is not enough. A system administrator grants or removes access for someone else, never for themselves, and every change is audited.
        System administrators always have compliance management; delegated administrators need it granted.
    </div></div>

    <section class="ac-card">
        <div class="ac-table-wrap"><table class="ac-table">
            <thead><tr><th>Administrator</th><th>Role</th><th>Compliance management</th><th>Identity documents</th><th></th></tr></thead>
            <tbody>@foreach($admins as $admin)
                @php
                    $isSystem = \App\Support\AdminAuthorization::isSystemAdmin($admin);
                    $p = $admin->adminPermissions;
                    $identity = (bool) ($p?->can_access_identity_documents);
                @endphp
                <tr>
                    <td>{{ $admin->name }}<div class="ac-hint">{{ $admin->email }}</div></td>
                    <td>{{ $isSystem ? 'System administrator' : 'Administrator' }}</td>
                    <td><span class="ac-badge {{ \App\Support\AdminAuthorization::isComplianceManager($admin) ? 'success' : '' }}">{{ $isSystem ? 'Yes (system administrator)' : ($p?->can_manage_compliance ? 'Granted' : 'No') }}</span></td>
                    <td><span class="ac-badge {{ $identity ? 'warning' : '' }}">{{ $identity ? 'Granted' . ($p->identity_access_granted_at ? ' ' . $p->identity_access_granted_at->format('j M Y') : '') : 'No' }}</span></td>
                    <td>
                        @can(\App\Support\AdminAuthorization::IDENTITY_ACCESS_GRANT, $admin)
                            <form method="POST" action="{{ route('admin.console.compliance-centre.access.update', $admin) }}" style="display:flex;gap:6px;flex-wrap:wrap;align-items:flex-end">@csrf
                                <div class="ac-field"><label for="g-{{ $admin->id }}">Identity documents</label>
                                    <select id="g-{{ $admin->id }}" class="ac-select" name="grant"><option value="0" @selected(!$identity)>No access</option><option value="1" @selected($identity)>Grant access</option></select></div>
                                @unless($isSystem)
                                    <div class="ac-field"><label for="m-{{ $admin->id }}">Compliance management</label>
                                        <select id="m-{{ $admin->id }}" class="ac-select" name="manage"><option value="0" @selected(!$p?->can_manage_compliance)>No</option><option value="1" @selected($p?->can_manage_compliance)>Granted</option></select></div>
                                @endunless
                                <div class="ac-field"><label for="why-{{ $admin->id }}">Reason</label><input id="why-{{ $admin->id }}" class="ac-input" name="reason" required minlength="10" maxlength="500"></div>
                                <label style="display:flex;gap:4px"><input type="checkbox" name="confirm" value="1" required> Confirm</label>
                                <button class="ac-btn ac-btn-sm" type="submit">Save</button>
                            </form>
                        @elseif(auth()->id() === $admin->id)
                            <span class="ac-hint">Another system administrator changes your access.</span>
                        @endcan
                    </td>
                </tr>
            @endforeach</tbody>
        </table></div>
    </section>
@endsection
