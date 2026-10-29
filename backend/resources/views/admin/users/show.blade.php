@extends('layouts.admin')

@section('title', $subject->name)
@section('breadcrumbs')
    <span aria-hidden="true">›</span><a href="{{ route('admin.console.users.index') }}">Users</a>
    <span aria-hidden="true">›</span><span aria-current="page">{{ $subject->name }}</span>
@endsection
@section('heading', $subject->name)
@section('subheading', 'Account profile, roles and event assignments.')

@section('content')
    @php
        $statusTone = ['active' => 'success', 'suspended' => 'danger', 'pending_verification' => 'warning'];
        $maskedPhone = $subject->phone ? '••• ••• ' . substr(preg_replace('/\D/', '', $subject->phone), -4) : null;
    @endphp

    <div class="ac-grid ac-grid-2">
        <section class="ac-card" aria-labelledby="profile-heading">
            <div class="ac-card-head"><h2 id="profile-heading">Profile</h2></div>
            <div class="ac-card-body">
                <dl class="ac-dl">
                    <dt>Email</dt><dd>{{ $subject->email }} @if($subject->email_verified_at)<span class="ac-badge success">Verified</span>@else<span class="ac-badge warning">Not verified</span>@endif</dd>
                    <dt>Phone</dt>
                    <dd>
                        @if(!$subject->phone)
                            —
                        @elseif($revealContact)
                            {{ $subject->phone }}
                        @else
                            {{ $maskedPhone }}
                            @if($canRevealContact)
                                <a class="ac-link" href="{{ route('admin.console.users.show', [$subject, 'reveal' => 1]) }}" style="margin-left:6px">Reveal</a>
                                <span class="ac-hint">(recorded in the audit log)</span>
                            @endif
                        @endif
                    </dd>
                    <dt>Status</dt><dd><span class="ac-badge {{ $statusTone[$subject->status] ?? '' }}">{{ ucfirst(str_replace('_', ' ', $subject->status)) }}</span></dd>
                    <dt>Registered</dt><dd>{{ $subject->created_at?->format('M j, Y') }}</dd>
                    <dt>Orders</dt><dd>{{ number_format($orderCount) }} {{ \Illuminate\Support\Str::plural('order', $orderCount) }}{{ \App\Support\AdminAuthorization::organizerScope(Auth::user()) === null ? '' : ' with your organizers' }}</dd>
                    <dt>Two-factor sign-in</dt><dd>{{ $subject->two_factor_confirmed_at ? 'On' : 'Off' }}</dd>
                </dl>
            </div>
        </section>

        <section class="ac-card" aria-labelledby="roles-heading">
            <div class="ac-card-head"><h2 id="roles-heading">Global roles</h2></div>
            <div class="ac-card-body">
                @foreach($roles as $role)
                    @php $type = \App\Enums\AccountType::tryFrom($role); @endphp
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--ac-line)">
                        <div>
                            <strong>{{ $type?->label() ?? $role }}</strong>
                            <span class="ac-cell-sub">{{ $type?->primaryPurpose() }}</span>
                        </div>
                        @if($canManageRoles && $role !== 'attendee' && Auth::user()->canAssignRole($subject, $role))
                            <details>
                                <summary class="ac-btn ac-btn-sm ac-btn-danger">Remove</summary>
                                <form method="POST" action="{{ route('admin.console.users.roles.revoke', [$subject, $role]) }}" class="ac-confirm" style="margin-top:8px;max-width:320px">
                                    @csrf
                                    <p>{{ $subject->name }} loses everything the {{ $type?->label() }} role allows, straight away.</p>
                                    <label class="ac-check"><input type="checkbox" name="confirm" value="1" required> I understand</label>
                                    <div class="ac-form-actions"><button class="ac-btn ac-btn-danger" type="submit" data-loading-text="Removing…">Remove role</button></div>
                                </form>
                            </details>
                        @endif
                    </div>
                @endforeach

                @if($canManageRoles && $grantableRoles->isNotEmpty())
                    <form method="POST" action="{{ route('admin.console.users.roles.grant', $subject) }}" style="margin-top:14px">
                        @csrf
                        <div class="ac-form-row">
                            <div class="ac-field">
                                <label for="grant-role">Add a role</label>
                                <select class="ac-select" id="grant-role" name="role" required>
                                    @foreach($grantableRoles as $role)
                                        <option value="{{ $role }}">{{ \App\Enums\AccountType::from($role)->label() }}</option>
                                    @endforeach
                                </select>
                                <span class="ac-hint">New administrators start with no permissions until you set them.</span>
                            </div>
                        </div>
                        <label class="ac-check" style="margin-top:10px"><input type="checkbox" name="confirm" value="1" required> I confirm this person should have this role</label>
                        <div class="ac-form-actions"><button class="ac-btn ac-btn-dark" type="submit" data-loading-text="Adding…">Add role</button></div>
                    </form>
                @elseif(!$canManageRoles)
                    <p class="ac-hint" style="margin:12px 0 0">Only a System Administrator can change roles, and never their own.</p>
                @endif
            </div>
        </section>
    </div>

    @if($adminPermissions)
        <section class="ac-card ac-section" aria-labelledby="admin-perm-heading">
            <div class="ac-card-head">
                <h2 id="admin-perm-heading">Administrator permissions</h2>
                @can(\App\Support\AdminAuthorization::ROLES_MANAGE, $subject)
                    <a class="ac-link" href="{{ route('admin.console.roles.index') }}#admin-{{ $subject->id }}">Edit</a>
                @endcan
            </div>
            <div class="ac-card-body">
                @foreach(\App\Http\Controllers\Admin\RoleController::CAPABILITIES as $flag => $capability)
                    <span class="ac-badge {{ $adminPermissions->{$flag} ? 'success' : '' }}" style="margin:0 6px 6px 0">{{ $capability['label'] }}: {{ $adminPermissions->{$flag} ? 'On' : 'Off' }}</span>
                @endforeach
                <p class="ac-hint" style="margin:6px 0 0">Scope: {{ $adminPermissions->scope === 'all' ? 'all organizers' : count($adminPermissions->assigned_organizer_ids ?? []) . ' assigned organizer(s)' }}</p>
            </div>
        </section>
    @endif

    <div class="ac-grid ac-grid-2 ac-section" style="margin-top:16px">
        <section class="ac-card" aria-labelledby="membership-heading">
            <div class="ac-card-head"><h2 id="membership-heading">Organizer teams</h2></div>
            @if($memberships->isEmpty())
                <div class="ac-empty"><strong>Not on any organizer team</strong>{{ \App\Support\AdminAuthorization::organizerScope(Auth::user()) === null ? '' : 'Only your organizers are listed.' }}</div>
            @else
                <div class="ac-table-wrap"><table class="ac-table">
                    <thead><tr><th scope="col">Organizer</th><th scope="col">Team role</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                    @foreach($memberships as $membership)
                        @php $teamRole = $membership->role instanceof \App\Enums\TenantRole ? $membership->role : \App\Enums\TenantRole::tryFrom((string) $membership->role); @endphp
                        <tr>
                            <td>{{ $membership->organizer?->name ?? 'Unknown organizer' }}</td>
                            <td>{{ $teamRole?->label() ?? $membership->role }}</td>
                            <td><span class="ac-badge {{ $membership->is_active ? 'success' : '' }}">{{ $membership->is_active ? 'Active' : 'Inactive' }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </section>

        <section class="ac-card" aria-labelledby="staff-heading">
            <div class="ac-card-head"><h2 id="staff-heading">Event staff assignments</h2></div>
            @if($assignments->isEmpty())
                <div class="ac-empty"><strong>No event staff assignments</strong></div>
            @else
                <div class="ac-table-wrap"><table class="ac-table">
                    <thead><tr><th scope="col">Event</th><th scope="col">Position</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                    @foreach($assignments as $assignment)
                        @php $position = \App\Enums\EventWorkerRole::tryFrom((string) ($assignment->worker_role instanceof \BackedEnum ? $assignment->worker_role->value : $assignment->worker_role)); @endphp
                        <tr>
                            <td>{{ $assignment->event?->title ?? 'All events' }}<span class="ac-cell-sub">{{ $assignment->event?->date_str }}</span></td>
                            <td>{{ $position?->label() ?? $assignment->worker_role }}</td>
                            <td><span class="ac-badge {{ $assignment->is_active ? 'success' : '' }}">{{ $assignment->is_active ? 'Active' : ucfirst((string) ($assignment->status ?? 'Inactive')) }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </section>
    </div>

    @if($canManageStatus)
        <section class="ac-card ac-section" aria-labelledby="status-heading" style="margin-top:16px">
            <div class="ac-card-head"><h2 id="status-heading">Account status</h2></div>
            <div class="ac-card-body">
                @php $suspending = $subject->status !== 'suspended'; @endphp
                <form method="POST" action="{{ route('admin.console.users.status', $subject) }}" class="ac-confirm">
                    @csrf
                    <input type="hidden" name="status" value="{{ $suspending ? 'suspended' : 'active' }}">
                    <h3>{{ $suspending ? 'Suspend this account' : 'Reactivate this account' }}</h3>
                    <p>{{ $suspending
                        ? 'They are signed out everywhere and cannot sign in, buy tickets or work at events until reactivated. Their orders and history are kept.'
                        : 'They can sign in again with their existing roles and assignments.' }}</p>
                    <div class="ac-field">
                        <label for="status-reason">Reason (saved in the audit log)</label>
                        <textarea class="ac-textarea" id="status-reason" name="reason" required minlength="5" maxlength="500" @error('reason') aria-invalid="true" aria-describedby="status-reason-error" @enderror>{{ old('reason') }}</textarea>
                        @error('reason')<span class="ac-error" id="status-reason-error">{{ $message }}</span>@enderror
                    </div>
                    <label class="ac-check" style="margin-top:10px"><input type="checkbox" name="confirm" value="1" required> I confirm this change</label>
                    <div class="ac-form-actions">
                        <button class="ac-btn {{ $suspending ? 'ac-btn-danger' : 'ac-btn-dark' }}" type="submit" data-loading-text="Saving…">{{ $suspending ? 'Suspend account' : 'Reactivate account' }}</button>
                    </div>
                </form>
            </div>
        </section>
    @endif

    @if($activity->isNotEmpty())
        <section class="ac-card ac-section" aria-labelledby="activity-heading" style="margin-top:16px">
            <div class="ac-card-head"><h2 id="activity-heading">Recent account activity</h2></div>
            <div class="ac-table-wrap"><table class="ac-table">
                <thead><tr><th scope="col">When</th><th scope="col">Action</th><th scope="col">Outcome</th></tr></thead>
                <tbody>
                @foreach($activity as $log)
                    <tr>
                        <td>{{ $log->created_at?->format('M j, Y g:i A') }}</td>
                        <td>{{ str_replace('_', ' ', $log->action) }}<span class="ac-cell-sub">{{ $log->user_id === $subject->id ? 'By this user' : 'On this account' }}</span></td>
                        <td><span class="ac-badge {{ ($log->outcome ?? 'success') === 'success' ? 'success' : 'danger' }}">{{ ucfirst($log->outcome ?? 'success') }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </section>
    @endif
@endsection
