@extends('layouts.admin')

@section('title', 'Users')
@section('breadcrumbs')<span aria-hidden="true">›</span><span aria-current="page">Users</span>@endsection
@section('heading', 'Users')
@section('subheading')
    @if(\App\Support\AdminAuthorization::organizerScope(Auth::user()) === null)
        All accounts on the platform.
    @else
        Accounts linked to your organizers: their team members, event staff and customers.
    @endif
@endsection

@section('content')
    @php
        $roleLabels = ['system_admin' => 'System Administrator', 'admin' => 'Administrator', 'event_host' => 'Event Host', 'staff' => 'Event staff', 'attendee' => 'Attendee only'];
        $statusTone = ['active' => 'success', 'suspended' => 'danger', 'pending_verification' => 'warning'];
    @endphp
    <div class="ac-card">
        <form class="ac-filters" method="GET" action="{{ route('admin.console.users.index') }}" role="search">
            <div class="ac-field grow">
                <label for="q">Search</label>
                <input class="ac-input" id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Name or email" autocomplete="off">
            </div>
            <div class="ac-field">
                <label for="role">Role</label>
                <select class="ac-select" id="role" name="role">
                    <option value="">All roles</option>
                    @foreach($roleLabels as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['role'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ac-field">
                <label for="status">Status</label>
                <select class="ac-select" id="status" name="status">
                    <option value="">Any status</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
            </div>
            <button class="ac-btn ac-btn-dark" type="submit" data-loading-text="Loading…">Apply</button>
            @if(array_filter($filters))<a class="ac-btn" href="{{ route('admin.console.users.index') }}">Clear</a>@endif
        </form>

        @if($users->isEmpty())
            <div class="ac-empty">
                @if(array_filter($filters))
                    <strong>No users match these filters</strong>Try a different name, role or status.
                @else
                    <strong>No users to show</strong>Nobody is linked to your organizers yet.
                @endif
            </div>
        @else
            <div class="ac-table-wrap">
                <table class="ac-table">
                    <caption class="sr-only">User accounts</caption>
                    <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Email</th>
                        <th scope="col">Roles</th>
                        <th scope="col">Status</th>
                        <th scope="col">Event staff</th>
                        <th scope="col">Registered</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($users as $user)
                        @php
                            // Built from the eager-loaded active roles to avoid a query per row.
                            $roles = $user->userRoles->pluck('role')
                                ->when($user->is_system_admin, fn ($r) => $r->push('system_admin'))
                                ->push('attendee')->unique()->values();
                        @endphp
                        <tr>
                            <td class="ac-cell-main">{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>
                                @foreach($roles as $role)
                                    <span class="ac-tag">{{ \App\Enums\AccountType::tryFrom($role)?->label() ?? $role }}</span>
                                @endforeach
                            </td>
                            <td><span class="ac-badge {{ $statusTone[$user->status] ?? '' }}">{{ ucfirst(str_replace('_', ' ', $user->status)) }}</span></td>
                            <td>{{ $user->active_assignments_count ? $user->active_assignments_count . ' active' : '—' }}</td>
                            <td><time datetime="{{ $user->created_at?->toDateString() }}">{{ $user->created_at?->format('M j, Y') }}</time></td>
                            <td class="num">
                                @can(\App\Support\AdminAuthorization::USER_VIEW, $user)
                                    <a class="ac-link" href="{{ route('admin.console.users.show', $user) }}">View<span class="sr-only"> {{ $user->name }}</span></a>
                                @else
                                    <span class="ac-hint" title="Only a system administrator can open this account">Restricted</span>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @include('admin.partials.pagination', ['paginator' => $users])
        @endif
    </div>
@endsection
