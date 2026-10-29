@extends('layouts.admin')

@section('title', $event->title)
@section('breadcrumbs')
    <span aria-hidden="true">›</span><a href="{{ route('admin.console.events.index') }}">Events</a>
    <span aria-hidden="true">›</span><span aria-current="page">{{ $event->title }}</span>
@endsection
@section('heading', $event->title)
@section('subheading')Hosted by {{ $event->organizer?->name ?? $event->organizer_name }}.@endsection
@section('actions')
    @if($canSeePayments)
        <a class="ac-btn" href="{{ route('admin.console.orders.index', ['event' => $event->id]) }}">View orders</a>
    @endif
@endsection

@section('content')
    @php $statusTone = ['published' => 'success', 'draft' => '', 'unpublished' => 'warning', 'cancelled' => 'danger']; @endphp
    <div class="ac-grid ac-grid-2">
        <section class="ac-card" aria-labelledby="details-heading">
            <div class="ac-card-head"><h2 id="details-heading">Event details</h2></div>
            <div class="ac-card-body">
                <dl class="ac-dl">
                    <dt>Publication</dt><dd><span class="ac-badge {{ $statusTone[$event->status] ?? '' }}">{{ ucfirst($event->status) }}</span></dd>
                    <dt>Ordering</dt><dd><span class="ac-badge {{ $event->is_ordering_open ? 'success' : '' }}">{{ $event->is_ordering_open ? 'Open' : 'Closed' }}</span></dd>
                    <dt>Date</dt><dd>{{ $event->date_str }}, {{ $event->time_str }} ({{ $event->timezone }})</dd>
                    <dt>Venue</dt><dd>{{ $event->venue_name }}<span class="ac-cell-sub">{{ $event->venue_address }}</span></dd>
                    <dt>Order deadline</dt><dd>{{ $event->advance_order_deadline?->format('M j, Y g:i A') ?? '—' }}</dd>
                    <dt>Currency</dt><dd>{{ $event->currency }}</dd>
                    <dt>Active staff</dt><dd>{{ $staffCount }}</dd>
                    <dt>Created</dt><dd>{{ $event->created_at?->format('M j, Y') }}</dd>
                </dl>
            </div>
        </section>

        <section class="ac-card" aria-labelledby="orders-heading">
            <div class="ac-card-head"><h2 id="orders-heading">Order activity</h2></div>
            <div class="ac-card-body">
                @if($ordersTotal === 0)
                    <p style="margin:0;color:var(--ac-text-2)">No orders yet.</p>
                @else
                    <div class="ac-metric-value">{{ number_format($ordersTotal) }} <span style="font-size:14px;font-weight:600;color:var(--ac-text-2)">orders, all time</span></div>
                    <div style="margin-top:10px">
                        @foreach($statusCounts as $status => $count)
                            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--ac-line)">
                                @include('admin.partials.status', ['status' => $status])
                                <strong>{{ number_format($count) }}</strong>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    </div>

    <section class="ac-card ac-section" aria-labelledby="moderation-heading" style="margin-top:16px">
        <div class="ac-card-head"><h2 id="moderation-heading">Moderation</h2></div>
        <div class="ac-card-body">
            @if(!$canModerate)
                <p style="margin:0;color:var(--ac-text-2)">You can view this event but not change it. Moderation needs the Event moderation permission for this organizer.</p>
            @elseif($actions === [])
                <p style="margin:0;color:var(--ac-text-2)">No moderation actions apply to an event that is {{ $event->status }}. Drafts and cancellations stay with the host.</p>
            @else
                @php
                    $explain = [
                        'unpublish' => 'Removes the event from public listings and stops new orders. Existing orders and tickets stay valid.',
                        'republish' => 'Puts the event back in public listings. Ordering stays closed until the host or you reopen it.',
                        'close_ordering' => 'Stops new food and ticket orders. The event stays visible.',
                        'open_ordering' => 'Lets attendees place orders again.',
                    ];
                @endphp
                @foreach($actions as $action => $label)
                    <form method="POST" action="{{ route('admin.console.events.moderate', $event->id) }}" class="ac-confirm">
                        @csrf
                        <input type="hidden" name="action" value="{{ $action }}">
                        <h3>{{ $label }}</h3>
                        <p>{{ $explain[$action] }}</p>
                        <div class="ac-field">
                            <label for="reason-{{ $action }}">Reason (shown in the audit log)</label>
                            <textarea class="ac-textarea" id="reason-{{ $action }}" name="reason" required minlength="5" maxlength="500"></textarea>
                        </div>
                        <label class="ac-check" style="margin-top:10px"><input type="checkbox" name="confirm" value="1" required> I confirm this change</label>
                        <div class="ac-form-actions">
                            <button class="ac-btn {{ in_array($action, ['unpublish', 'close_ordering'], true) ? 'ac-btn-danger' : 'ac-btn-dark' }}" type="submit" data-loading-text="Saving…">{{ $label }}</button>
                        </div>
                    </form>
                @endforeach
            @endif
        </div>
    </section>
@endsection
