@extends('layouts.admin')

@section('title', 'Fees & Taxes')
@section('heading', 'Fees, taxes and discounts')
@section('subheading', 'Configured charges added to customer orders. Nothing here is applied unless an administrator sets it up.')
@section('breadcrumbs')<span> / Fees &amp; taxes</span>@endsection

@php
    $kindLabels = ['platform_fee' => 'EventBite fee', 'host_fee' => 'Organizer fee', 'tax' => 'Tax', 'discount' => 'Discount'];
    $methodLabels = ['percent' => 'Percentage', 'fixed_per_order' => 'Fixed per order', 'fixed_per_item' => 'Fixed per item'];
    $appliesLabels = ['all' => 'Tickets and food', 'tickets' => 'Tickets only', 'food' => 'Food only'];
@endphp

@section('content')
    <div class="ac-alert" role="note">
        Tax rates are not built in and are not verified by EventBite. Only add a tax rule with a rate you have confirmed
        for that jurisdiction and kind of sale; record the source in the notes. Customers see each fee and tax as its own
        line, with the full total, before they confirm.
    </div>

    <section class="ac-card" style="margin:18px 0;padding:18px" aria-labelledby="add-rule">
        <h2 id="add-rule">Add a rule</h2>
        <form method="POST" action="{{ route('admin.console.charges.store') }}" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px;align-items:end">
            @csrf
            <label>Type
                <select name="kind" class="ac-input" required>
                    @foreach($kindLabels as $value => $label)<option value="{{ $value }}" @selected(old('kind') === $value)>{{ $label }}</option>@endforeach
                </select>
            </label>
            <label>Label customers see <input name="label" maxlength="120" required class="ac-input" value="{{ old('label') }}" placeholder="e.g. Service fee"></label>
            <label>How it is calculated
                <select name="method" class="ac-input" required>
                    @foreach($methodLabels as $value => $label)<option value="{{ $value }}" @selected(old('method') === $value)>{{ $label }}</option>@endforeach
                </select>
            </label>
            <label>Rate (%) — for percentages <input name="rate_percent" inputmode="decimal" class="ac-input" value="{{ old('rate_percent') }}" placeholder="e.g. 5"></label>
            <label>Amount — for fixed charges <input name="amount" inputmode="decimal" class="ac-input" value="{{ old('amount') }}" placeholder="e.g. 1.50"></label>
            <label>Currency — for fixed charges
                <select name="currency" class="ac-input">
                    @foreach($currencies as $code)<option value="{{ $code }}" @selected(old('currency', $defaultCurrency) === $code)>{{ $code }}</option>@endforeach
                </select>
            </label>
            <label>Applies to
                <select name="applies_to" class="ac-input">
                    @foreach($appliesLabels as $value => $label)<option value="{{ $value }}" @selected(old('applies_to') === $value)>{{ $label }}</option>@endforeach
                </select>
            </label>
            <label>Organizer (blank = every organizer)
                <select name="organizer_id" class="ac-input">
                    <option value="">All organizers</option>
                    @foreach($organizers as $id => $name)<option value="{{ $id }}" @selected(old('organizer_id') === $id)>{{ $name }}</option>@endforeach
                </select>
            </label>
            <label>Only events in state (optional)
                <select name="state_code" class="ac-input">
                    <option value="">Any state</option>
                    @foreach($states as $state)<option value="{{ $state->code }}" @selected(old('state_code') === $state->code)>{{ $state->name }} ({{ $state->code }})</option>@endforeach
                </select>
            </label>
            <label style="grid-column:1/-1">Notes (source of the rate, who confirmed it, when) <input name="notes" maxlength="1000" class="ac-input" value="{{ old('notes') }}"></label>
            <button class="ac-btn ac-btn-primary" type="submit">Add rule</button>
        </form>
    </section>

    <section class="ac-card" style="margin:18px 0;padding:18px" aria-labelledby="rules-heading">
        <h2 id="rules-heading">Rules</h2>
        @if($rules->isEmpty())
            <p style="margin:0;color:var(--ac-text-2)">No fees, taxes or discounts are configured. Orders are charged item prices plus each event's own packaging fee.</p>
        @else
            <div style="overflow:auto">
                <table class="ac-table">
                    <thead><tr><th>Status</th><th>Type</th><th>Label</th><th>Charge</th><th>Applies to</th><th>Scope</th><th>Notes</th><th></th></tr></thead>
                    <tbody>
                    @foreach($rules as $rule)
                        <tr>
                            <td>{{ $rule->is_active ? 'On' : 'Off' }}</td>
                            <td>{{ $kindLabels[$rule->kind] ?? $rule->kind }}</td>
                            <td>{{ $rule->label }}</td>
                            <td class="num">
                                @if($rule->method === 'percent')
                                    {{ intdiv((int) $rule->rate_bp, 100) }}.{{ str_pad((string) ((int) $rule->rate_bp % 100), 2, '0', STR_PAD_LEFT) }}%
                                @else
                                    {{ \App\Support\Money::formatMinor((int) $rule->amount_minor, $rule->currency) }} {{ $rule->currency }}
                                    {{ $rule->method === 'fixed_per_item' ? 'per item' : 'per order' }}
                                @endif
                            </td>
                            <td>{{ $appliesLabels[$rule->applies_to] ?? $rule->applies_to }}</td>
                            <td>{{ $rule->organizer_id ? ($organizers[$rule->organizer_id] ?? 'One organizer') : 'All organizers' }}{{ $rule->state_code ? ' · ' . $rule->state_code . ' events' : '' }}</td>
                            <td>{{ $rule->notes }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.console.charges.toggle', $rule) }}">
                                    @csrf
                                    <button class="ac-btn ac-btn-secondary" type="submit">{{ $rule->is_active ? 'Switch off' : 'Switch on' }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
