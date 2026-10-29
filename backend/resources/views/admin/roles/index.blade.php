@extends('layouts.admin')

@section('title', 'Roles & Permissions')
@section('breadcrumbs')<span aria-hidden="true">›</span><span aria-current="page">Roles &amp; Permissions</span>@endsection
@section('heading', 'Roles & Permissions')
@section('subheading', 'Who can do what on EventBite. Global roles are given per account; event staff positions are given per event by the event host.')

@section('content')
    <section class="ac-card" aria-labelledby="global-heading">
        <div class="ac-card-head"><h2 id="global-heading">Global roles</h2></div>
        <div class="ac-table-wrap"><table class="ac-table">
            <thead><tr><th scope="col">Role</th><th scope="col">Purpose</th><th scope="col">Who assigns it</th><th scope="col" class="num">Accounts</th></tr></thead>
            <tbody>
            @php
                $assignedBy = [
                    'system_admin' => 'Another System Administrator only. The last one cannot be removed.',
                    'admin' => 'A System Administrator. Starts with no permissions.',
                    'event_host' => 'A System Administrator, or an approved host application.',
                    'attendee' => 'Every account has it automatically.',
                ];
            @endphp
            @foreach($accountTypes as $type)
                <tr>
                    <td class="ac-cell-main">{{ $type->label() }}</td>
                    <td>{{ $type->primaryPurpose() }}</td>
                    <td>{{ $assignedBy[$type->value] ?? '' }}</td>
                    <td class="num">{{ number_format($roleCounts[$type->value] ?? 0) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </section>

    <section class="ac-card ac-section" aria-labelledby="staff-heading" style="margin-top:16px">
        <div class="ac-card-head"><h2 id="staff-heading">Event staff positions</h2></div>
        <div class="ac-table-wrap"><table class="ac-table">
            <thead><tr><th scope="col">Position</th><th scope="col">Can</th><th scope="col">Cannot</th><th scope="col" class="num">Active</th></tr></thead>
            <tbody>
            @foreach($staffRoles as $position)
                <tr>
                    <td class="ac-cell-main">{{ $position->label() }}</td>
                    <td>{{ $position->canDoDescription() }}</td>
                    <td>{{ $position->mustNotDoDescription() }}</td>
                    <td class="num">{{ number_format($staffCounts[$position->value] ?? 0) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </section>

    <section class="ac-card ac-section" aria-labelledby="admins-heading" style="margin-top:16px">
        <div class="ac-card-head"><h2 id="admins-heading">Delegated administrators</h2></div>
        @if(!$canManage)
            <div class="ac-empty"><strong>Only System Administrators can view or change administrator permissions</strong>Ask a System Administrator if you need more access.</div>
        @elseif($administrators->isEmpty())
            <div class="ac-empty"><strong>No delegated administrators yet</strong>Give someone the Administrator role from their user page, then set their permissions here.</div>
        @else
            <div class="ac-card-body">
                @foreach($administrators as $admin)
                    @php
                        $perm = $admin->adminPermissions;
                        $isSelf = $admin->id === Auth::id();
                        $assigned = $perm?->assigned_organizer_ids ?? [];
                    @endphp
                    <form method="POST" action="{{ route('admin.console.roles.administrators.update', $admin) }}" class="ac-confirm" id="admin-{{ $admin->id }}">
                        @csrf
                        <h3><a class="ac-link" href="{{ route('admin.console.users.show', $admin) }}">{{ $admin->name }}</a> <span class="ac-cell-sub" style="display:inline">{{ $admin->email }}</span></h3>
                        @if($isSelf)
                            <p>You cannot change your own permissions.</p>
                        @else
                            <fieldset style="border:0;padding:0;margin:0 0 12px">
                                <legend class="ac-label" style="margin-bottom:6px">Capabilities</legend>
                                <div class="ac-form-row">
                                    @foreach($capabilities as $flag => $capability)
                                        <label class="ac-check">
                                            <input type="hidden" name="{{ $flag }}" value="0">
                                            <input type="checkbox" name="{{ $flag }}" value="1" @checked($perm?->{$flag})>
                                            <span><strong>{{ $capability['label'] }}</strong><span class="ac-cell-sub">{{ $capability['consequence'] }}</span></span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                            <div class="ac-form-row">
                                <div class="ac-field">
                                    <label for="scope-{{ $admin->id }}">Organizer scope</label>
                                    <select class="ac-select" id="scope-{{ $admin->id }}" name="scope">
                                        <option value="assigned_events" @selected(($perm?->scope ?? 'assigned_events') !== 'all')>Only the organizers ticked below</option>
                                        <option value="all" @selected($perm?->scope === 'all')>All organizers on the platform</option>
                                    </select>
                                    <span class="ac-hint">"All organizers" lets them see every host's events and orders.</span>
                                </div>
                                <div class="ac-field">
                                    <span class="ac-label">Assigned organizers</span>
                                    <div style="max-height:150px;overflow:auto;border:1px solid var(--ac-line);border-radius:8px;padding:8px;background:#fff">
                                        @forelse($organizers as $organizer)
                                            <label class="ac-check"><input type="checkbox" name="organizer_ids[]" value="{{ $organizer->id }}" @checked(in_array((string) $organizer->id, array_map('strval', $assigned), true))> {{ $organizer->name }}</label>
                                        @empty
                                            <span class="ac-hint">No organizers yet.</span>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                            <label class="ac-check" style="margin-top:12px"><input type="checkbox" name="confirm" value="1" required> I have checked what these permissions allow</label>
                            <div class="ac-form-actions"><button class="ac-btn ac-btn-dark" type="submit" data-loading-text="Saving…">Save permissions</button></div>
                        @endif
                    </form>
                @endforeach
            </div>
        @endif
    </section>
@endsection
