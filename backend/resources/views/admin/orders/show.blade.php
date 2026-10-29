@extends('layouts.admin')

@section('title', 'Order ' . $order->order_number)
@section('breadcrumbs')
    <span aria-hidden="true">›</span><a href="{{ route('admin.console.orders.index') }}">Orders &amp; Payments</a>
    <span aria-hidden="true">›</span><span aria-current="page">{{ $order->order_number }}</span>
@endsection
@section('heading')Order {{ $order->order_number }}@endsection
@section('subheading'){{ $order->event?->title ?? 'No event' }} · Ticket {{ $order->ticket_code }}@endsection

@section('content')
    <div class="ac-grid ac-grid-2">
        <section class="ac-card" aria-labelledby="payment-heading">
            <div class="ac-card-head"><h2 id="payment-heading">Payment</h2>@include('admin.partials.status', ['status' => $order->status])</div>
            <div class="ac-card-body">
                <dl class="ac-dl">
                    <dt>Method</dt><dd>{{ \App\Support\PaymentStatusPresenter::methodLabel($order->method) }}</dd>
                    <dt>Order total</dt><dd>{{ $order->currency }} {{ number_format((float) $order->total_amount, 2) }}</dd>
                    <dt>Amount verified</dt><dd>{{ $order->currency }} {{ number_format((float) $order->amount_paid, 2) }}</dd>
                    <dt>Transfer reference</dt><dd>{{ $order->payment_reference ?: '—' }}</dd>
                    <dt>Placed</dt><dd>{{ $order->created_at?->format('M j, Y g:i A') }}</dd>
                    <dt>Verified</dt><dd>{{ $order->verified_at ? $order->verified_at->format('M j, Y g:i A') . ' by ' . $order->verified_by : '—' }}</dd>
                    @if($order->rejection_reason)<dt>Rejection reason</dt><dd>{{ $order->rejection_reason }}</dd>@endif
                    @if($order->verification_notes)<dt>Notes</dt><dd>{{ $order->verification_notes }}</dd>@endif
                    @if($order->status === 'refunded' && is_array($order->refund_details))
                        <dt>Refund</dt><dd>{{ $order->refund_details['reason'] ?? 'Refunded' }}</dd>
                    @endif
                    <dt>Last updated</dt><dd>{{ $order->updated_at?->format('M j, Y g:i A') }}</dd>
                </dl>
            </div>
        </section>

        <section class="ac-card" aria-labelledby="customer-heading">
            <div class="ac-card-head"><h2 id="customer-heading">Customer</h2></div>
            <div class="ac-card-body">
                @if($revealCustomer)
                    <dl class="ac-dl">
                        <dt>Name</dt><dd>{{ $order->customer_name }}</dd>
                        <dt>Phone</dt><dd>{{ $order->customer_phone }}</dd>
                        <dt>Email</dt><dd>{{ $order->email ?: '—' }}</dd>
                        <dt>Paid by</dt><dd>{{ $order->sender_name ?: '—' }}</dd>
                    </dl>
                    <p class="ac-hint" style="margin:10px 0 0">This view was recorded in the audit log.</p>
                @else
                    <dl class="ac-dl">
                        <dt>Name</dt><dd>{{ \Illuminate\Support\Str::of($order->customer_name)->explode(' ')->map(fn ($part, $i) => $i === 0 ? $part : mb_substr($part, 0, 1) . '.')->implode(' ') }}</dd>
                        <dt>Phone</dt><dd>••• {{ substr(preg_replace('/\D/', '', (string) $order->customer_phone), -3) }}</dd>
                    </dl>
                    @if($canSeeCustomer)
                        <a class="ac-btn" style="margin-top:12px" href="{{ route('admin.console.orders.show', [$order->id, 'reveal' => 1]) }}">Show full contact details</a>
                        <p class="ac-hint">Opening them is recorded in the audit log.</p>
                    @else
                        <p class="ac-hint" style="margin:10px 0 0">Full contact details are limited to System Administrators.</p>
                    @endif
                @endif
            </div>
        </section>
    </div>

    <section class="ac-card ac-section" aria-labelledby="items-heading" style="margin-top:16px">
        <div class="ac-card-head"><h2 id="items-heading">Items</h2></div>
        <div class="ac-table-wrap"><table class="ac-table">
            <thead><tr><th scope="col">Item</th><th scope="col" class="num">Qty</th><th scope="col" class="num">Collected</th><th scope="col" class="num">Line total</th></tr></thead>
            <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->name }}@if(!empty($item->selected_options_summary))<span class="ac-cell-sub">{{ implode(', ', (array) $item->selected_options_summary) }}</span>@endif</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ $item->collected_quantity }}</td>
                    <td class="num">{{ $item->currency }} {{ number_format((float) $item->final_item_price * $item->quantity, 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </section>

    <section class="ac-card ac-section" aria-labelledby="review-heading" style="margin-top:16px">
        <div class="ac-card-head"><h2 id="review-heading">Payment review</h2></div>
        <div class="ac-card-body">
            @if(!$canReview)
                <p style="margin:0;color:var(--ac-text-2)">
                    @if($order->status === 'refunded')
                        Refunded orders cannot change payment status.
                    @else
                        You can see this order but not verify it. Payment decisions are made by the event owner, the event's payment verifiers or a System Administrator.
                    @endif
                </p>
            @else
                <form method="POST" action="{{ route('admin.console.orders.review', $order->id) }}">
                    @csrf
                    <div class="ac-form-row">
                        <div class="ac-field">
                            <label for="review-status">Decision</label>
                            <select class="ac-select" id="review-status" name="status" required @error('status') aria-invalid="true" @enderror>
                                <option value="paid" @selected(old('status') === 'paid')>Verified: full amount received</option>
                                <option value="partially_paid" @selected(old('status') === 'partially_paid')>Partially verified: part received</option>
                                <option value="under_verification" @selected(old('status') === 'under_verification')>Still checking</option>
                                <option value="rejected" @selected(old('status') === 'rejected')>Rejected: payment not found</option>
                            </select>
                        </div>
                        <div class="ac-field">
                            <label for="amount-paid">Amount received ({{ $order->currency }})</label>
                            <input class="ac-input" id="amount-paid" name="amount_paid" type="number" min="0.01" step="0.01" value="{{ old('amount_paid') }}" @error('amount_paid') aria-invalid="true" @enderror>
                            <span class="ac-hint">Only for a partial payment.</span>
                            @error('amount_paid')<span class="ac-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="ac-field" style="margin-top:12px">
                        <label for="rejection-reason">Rejection reason (sent to the customer)</label>
                        <textarea class="ac-textarea" id="rejection-reason" name="rejection_reason" maxlength="500" @error('rejection_reason') aria-invalid="true" @enderror>{{ old('rejection_reason') }}</textarea>
                        @error('rejection_reason')<span class="ac-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="ac-field" style="margin-top:12px">
                        <label for="notes">Internal note</label>
                        <textarea class="ac-textarea" id="notes" name="notes" maxlength="500">{{ old('notes') }}</textarea>
                    </div>
                    <label class="ac-check" style="margin-top:12px"><input type="checkbox" name="confirm" value="1" required> I checked the bank statement or cash record for this payment</label>
                    <div class="ac-form-actions"><button class="ac-btn ac-btn-primary" type="submit" data-loading-text="Saving…">Save decision</button></div>
                </form>
            @endif
        </div>
    </section>
@endsection
