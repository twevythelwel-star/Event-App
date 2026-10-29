@extends('layouts.admin')

@section('title', 'Events')
@section('breadcrumbs')<span aria-hidden="true">›</span><span aria-current="page">Events</span>@endsection
@section('heading', 'Events')
@section('subheading', 'Every event in your scope, its host, publication state and order activity.')

@section('content')
    @php $statusTone = ['published' => 'success', 'draft' => '', 'unpublished' => 'warning', 'cancelled' => 'danger']; @endphp
    <div class="ac-card">
        <form class="ac-filters" method="GET" action="{{ route('admin.console.events.index') }}" role="search">
            <div class="ac-field grow">
                <label for="q">Search</label>
                <input class="ac-input" id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Event or host name">
            </div>
            <div class="ac-field">
                <label for="status">Publication</label>
                <select class="ac-select" id="status" name="status">
                    <option value="">Any</option>
                    @foreach($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>@endforeach
                </select>
            </div>
            <div class="ac-field">
                <label for="ordering">Ordering</label>
                <select class="ac-select" id="ordering" name="ordering">
                    <option value="">Any</option>
                    <option value="open" @selected(($filters['ordering'] ?? '') === 'open')>Open</option>
                    <option value="closed" @selected(($filters['ordering'] ?? '') === 'closed')>Closed</option>
                </select>
            </div>
            <button class="ac-btn ac-btn-dark" type="submit">Apply</button>
            @if(array_filter($filters))<a class="ac-btn" href="{{ route('admin.console.events.index') }}">Clear</a>@endif
        </form>

        @if($events->isEmpty())
            <div class="ac-empty">
                @if(array_filter($filters))<strong>No events match these filters</strong>Try other filters.@else<strong>No events in your scope yet</strong>Events appear here once a host in your organizers creates one.@endif
            </div>
        @else
            <div class="ac-table-wrap"><table class="ac-table">
                <caption class="sr-only">Events</caption>
                <thead><tr>
                    <th scope="col">Event</th><th scope="col">Host</th><th scope="col">Date</th><th scope="col">Publication</th>
                    <th scope="col">Ordering</th><th scope="col" class="num">Orders</th><th scope="col" class="num">Verified</th><th scope="col" class="num">To review</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr></thead>
                <tbody>
                @foreach($events as $event)
                    <tr>
                        <td class="ac-cell-main">{{ $event->title }}</td>
                        <td style="min-width:180px">{{ $event->organizer?->name ?? $event->organizer_name }}</td>
                        <td style="min-width:190px">{{ $event->date_str }}<span class="ac-cell-sub">{{ $event->time_str }}</span></td>
                        <td><span class="ac-badge {{ $statusTone[$event->status] ?? '' }}">{{ ucfirst($event->status) }}</span></td>
                        <td><span class="ac-badge {{ $event->is_ordering_open ? 'success' : '' }}">{{ $event->is_ordering_open ? 'Open' : 'Closed' }}</span></td>
                        <td class="num">{{ number_format($event->orders_total) }}</td>
                        <td class="num">{{ number_format($event->orders_verified) }}</td>
                        <td class="num">{{ $event->orders_awaiting_review ? number_format($event->orders_awaiting_review) : '—' }}</td>
                        <td class="num"><a class="ac-link" href="{{ route('admin.console.events.show', $event->id) }}">View<span class="sr-only"> {{ $event->title }}</span></a></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            @include('admin.partials.pagination', ['paginator' => $events])
        @endif
    </div>
@endsection
