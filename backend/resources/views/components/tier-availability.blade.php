{{--
    Availability badge for one ticket type, from TicketTierCatalog::sellable(). Says only what the data supports:
    sold out (stock limit reached), sales ended / not started (sale window), or a low-stock count.
--}}
@props(['tier'])

@if($tier['sold_out'])
    <span class="eb-badge eb-badge-error">Sold out</span>
@elseif($tier['sale_window'] === 'ended')
    <span class="eb-badge eb-badge-info">Sales ended</span>
@elseif($tier['sale_window'] === 'not_started')
    <span class="eb-badge eb-badge-info">Not on sale yet</span>
@elseif($tier['available'] !== null && $tier['available'] <= 10)
    <span class="eb-badge eb-badge-warning">{{ $tier['available'] }} left</span>
@endif
