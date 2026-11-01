{{-- Itemized receipt for emails (inline styles for mail clients). $r comes from ReceiptService::forOrder(). --}}
@php
    $cell = 'padding:6px 0;border-bottom:1px solid #eef1f5;vertical-align:top;';
    $num = $cell . 'text-align:right;white-space:nowrap;';
@endphp
<div style="margin-top:24px;padding-top:16px;border-top:2px solid #e4e9ef;font-size:14px;line-height:1.5;">
    <h2 style="margin:0 0 4px;font-size:17px;">Receipt · Order {{ $r['order_reference'] }}</h2>
    <p style="margin:0 0 12px;color:#4b5a6e;">
        <strong style="color:#142033;">{{ $r['event_name'] }}</strong><br>
        @if($r['event_date']){{ $r['event_date'] }}@endif @if($r['event_time'])· {{ $r['event_time'] }}@endif<br>
        @if($r['venue']){{ $r['venue'] }}@endif
    </p>

    <table role="presentation" style="width:100%;border-collapse:collapse;font-size:14px;">
        @foreach(['tickets' => 'Tickets', 'food' => 'Food'] as $key => $heading)
            @if($r[$key])
                <tr><td colspan="3" style="padding:10px 0 4px;font-weight:bold;">{{ $heading }}</td></tr>
                @foreach($r[$key] as $line)
                    <tr>
                        <td style="{{ $cell }}">{{ $line['name'] }}@if($line['details'])<br><span style="color:#6b7a8f;font-size:12px;">{{ $line['details'] }}</span>@endif</td>
                        <td style="{{ $num }}color:#4b5a6e;">{{ $line['quantity'] }} × {{ $line['unit_price'] }}</td>
                        <td style="{{ $num }}">{{ $line['line_total'] }}</td>
                    </tr>
                @endforeach
            @endif
        @endforeach
        <tr><td colspan="2" style="padding:10px 0 4px;">Subtotal</td><td style="padding:10px 0 4px;text-align:right;">{{ $r['subtotal'] }}</td></tr>
        @foreach($r['charges'] as $charge)
            <tr><td colspan="2" style="padding:2px 0;color:#4b5a6e;">{{ $charge['label'] }}</td><td style="padding:2px 0;text-align:right;color:#4b5a6e;">{{ $charge['amount'] }}</td></tr>
        @endforeach
        <tr><td colspan="2" style="padding:8px 0;font-weight:bold;border-top:1px solid #d8dee7;">Total ({{ $r['currency'] }})</td><td style="padding:8px 0;text-align:right;font-weight:bold;border-top:1px solid #d8dee7;">{{ $r['total'] }}</td></tr>
        <tr><td colspan="2" style="padding:2px 0;">Paid</td><td style="padding:2px 0;text-align:right;">{{ $r['amount_paid'] }}</td></tr>
        @if($r['refunded'])<tr><td colspan="2" style="padding:2px 0;">Refunded</td><td style="padding:2px 0;text-align:right;">{{ $r['refunded'] }}</td></tr>@endif
        @if($r['balance_due'])<tr><td colspan="2" style="padding:2px 0;font-weight:bold;">Balance due</td><td style="padding:2px 0;text-align:right;font-weight:bold;">{{ $r['balance_due'] }}</td></tr>@endif
    </table>

    <p style="margin:12px 0 0;color:#4b5a6e;">
        Payment: <strong style="color:#142033;">{{ $r['payment_status'] }}</strong> · {{ $r['payment_method'] }}
        @if($r['payment_reference'])<br>Your payment reference: {{ $r['payment_reference'] }}@endif
        @if($r['verified_at'])<br>Confirmed {{ $r['verified_at'] }}@endif
        @if($r['placed_at'])<br>Ordered {{ $r['placed_at'] }}@endif
        <br>Ticket code: {{ $r['ticket_code'] }}
    </p>
    @unless($r['payment_settled'])
        <p style="margin:8px 0 0;color:#8a5a00;">This is not proof of payment. Your ticket becomes valid once the payment is confirmed.</p>
    @endunless
    <p style="margin:12px 0 0;"><a href="{{ $r['ticket_url'] }}" style="color:#1d4ed8;">View your ticket and order</a></p>
</div>
