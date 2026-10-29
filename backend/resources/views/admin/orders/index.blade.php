@extends('layouts.admin')

@section('title', 'Orders & Payments')
@section('breadcrumbs')<span aria-hidden="true">›</span><span aria-current="page">Orders &amp; Payments</span>@endsection
@section('heading', 'Orders & Payments')
@section('subheading', 'Every order carries one ticket code. Payment status follows the order: Pending → Confirmation submitted → Verified or Rejected, and Refunded afterwards.')

@section('content')
    <div class="ac-card">
        <form class="ac-filters" method="GET" action="{{ route('admin.console.orders.index') }}" role="search">
            <div class="ac-field grow">
                <label for="q">Order or ticket code</label>
                <input class="ac-input" id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="e.g. ORD-2026-9731">
            </div>
            <div class="ac-field">
                <label for="status">Payment status</label>
                <select class="ac-select" id="status" name="status">
                    <option value="">Any</option>
                    @foreach($groups as $key => $group)<option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $group['label'] }}</option>@endforeach
                </select>
            </div>
            <div class="ac-field">
                <label for="method">Method</label>
                <select class="ac-select" id="method" name="method">
                    <option value="">Any</option>
                    @foreach(['bank', 'cash', 'card', 'zelle', 'cashapp'] as $method)<option value="{{ $method }}" @selected(($filters['method'] ?? '') === $method)>{{ \App\Support\PaymentStatusPresenter::methodLabel($method) }}</option>@endforeach
                </select>
            </div>
            <div class="ac-field">
                <label for="event">Event</label>
                <select class="ac-select" id="event" name="event">
                    <option value="">All events</option>
                    @foreach($events as $event)<option value="{{ $event->id }}" @selected(($filters['event'] ?? '') === (string) $event->id)>{{ $event->title }}</option>@endforeach
                </select>
            </div>
            <div class="ac-field" style="min-width:140px">
                <label for="from">Placed from</label>
                <input class="ac-input" id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="ac-field" style="min-width:140px">
                <label for="to">to</label>
                <input class="ac-input" id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}">
            </div>
            <button class="ac-btn ac-btn-dark" type="submit">Apply</button>
            @if(array_filter($filters))<a class="ac-btn" href="{{ route('admin.console.orders.index') }}">Clear</a>@endif
        </form>

        @if($orders->isEmpty())
            <div class="ac-empty">
                @if(array_filter($filters))<strong>No orders match these filters</strong>Try a wider date range or another status.@else<strong>No orders yet</strong>Orders appear once attendees check out.@endif
            </div>
        @else
            <div class="ac-table-wrap"><table class="ac-table">
                <caption class="sr-only">Orders</caption>
                <thead><tr>
                    <th scope="col">Order</th><th scope="col">Event</th><th scope="col">Method</th><th scope="col" class="num">Amount</th>
                    <th scope="col">Payment status</th><th scope="col">Placed</th><th scope="col">Verified</th><th scope="col"><span class="sr-only">Actions</span></th>
                </tr></thead>
                <tbody>
                @foreach($orders as $order)
                    <tr>
                        <td class="ac-cell-main">{{ $order->order_number }}<span class="ac-cell-sub">Ticket {{ $order->ticket_code }}</span></td>
                        <td>{{ $order->event?->title ?? '—' }}</td>
                        <td>{{ \App\Support\PaymentStatusPresenter::methodLabel($order->method) }}</td>
                        <td class="num">{{ $order->currency }} {{ number_format((float) $order->total_amount, 2) }}</td>
                        <td>@include('admin.partials.status', ['status' => $order->status])</td>
                        <td><time datetime="{{ $order->created_at?->toIso8601String() }}">{{ $order->created_at?->format('M j, g:i A') }}</time></td>
                        <td>{{ $order->verified_at?->format('M j, g:i A') ?? '—' }}</td>
                        <td class="num"><a class="ac-link" href="{{ route('admin.console.orders.show', $order->id) }}">View<span class="sr-only"> order {{ $order->order_number }}</span></a></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            @include('admin.partials.pagination', ['paginator' => $orders])
        @endif
    </div>
    @unless($canSeeCustomers)
        <p class="ac-hint" style="margin-top:10px">Customer names and contact details are hidden. Only System Administrators can open them, and each view is recorded.</p>
    @endunless
@endsection
