@extends('layouts.app')

@section('title', 'Ticketing — ' . $event->title)
@section('page-title', 'Smarter ticketing')

@section('content')
@php
    $tabs = ['pricing' => 'Pricing', 'codes' => 'Discount codes', 'promoters' => 'Promoters', 'complimentary' => 'Complimentary', 'waitlist' => 'Waitlist', 'settings' => 'Settings'];
    $fmt = fn (?int $minor) => \App\Support\Money::formatMinor((int) $minor, $currency);
    $major = fn ($minor) => $minor === null ? '' : number_format(((int) $minor) / 100, 2, '.', '');
    $local = fn (?string $iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->setTimezone($tz)->format('Y-m-d\TH:i') : '';
    $tierName = $tiers->pluck('name', 'id');
@endphp
<div class="eb-stack" style="gap: var(--space-5);">
    <div>
        <p class="eb-text-supporting" style="margin: 0;"><a href="{{ route('events.edit', $event->id) }}">← {{ $event->title }}</a></p>
        <h1 class="eb-text-page-title" style="margin: 0;">Smarter ticketing</h1>
        <p class="eb-text-supporting" style="margin: 0;">Prices that change by date or by sales, limited releases, group prices, bundles, codes, promoters, free tickets, transfers and waitlists. Times are in the event's time zone ({{ $tz }}).</p>
    </div>
    @if($errors->any())
        <x-alert type="error" title="Not saved"><ul style="margin: 4px 0 0; padding-left: 1.1rem;">@foreach($errors->all() as $m)<li>{{ $m }}</li>@endforeach</ul></x-alert>
    @endif
    <nav class="eb-tabs" role="tablist" aria-label="Ticketing">
        @foreach($tabs as $key => $label)
            <a href="{{ route('events.ticketing', ['id' => $event->id, 'tab' => $key]) }}" role="tab" id="tab-{{ $key }}" aria-controls="panel-{{ $key }}"
               aria-selected="{{ $tab === $key ? 'true' : 'false' }}" class="eb-tab {{ $tab === $key ? 'is-active' : '' }}">{{ $label }}</a>
        @endforeach
    </nav>

    {{-- Pricing per ticket type --}}
    <section role="tabpanel" id="panel-pricing" aria-labelledby="tab-pricing" class="eb-stack" style="gap: var(--space-4);" @if($tab !== 'pricing') hidden @endif>
        @forelse($tiers as $tier)
            @php
                $tid = (string) $tier['id'];
                $steps = array_pad((array) ($tier['price_steps'] ?? []), 4, []);
                $releases = array_pad((array) ($tier['releases'] ?? []), 3, []);
                $groups = array_pad((array) ($tier['group_discounts'] ?? []), 2, []);
                $bundle = array_pad((array) ($tier['bundle_items'] ?? []), 3, []);
                $stock = \App\Services\Checkout\TicketTierCatalog::available($tier);
            @endphp
            <details class="eb-panel" style="margin: 0;" @if($loop->first) open @endif>
                <summary class="eb-schedule-studio-row"><strong>{{ $tier['name'] ?? 'Ticket' }}</strong>
                    <span class="eb-help-text">{{ $sold[$tid] ?? 0 }} sold · {{ $stock === null ? 'no stock limit' : $stock . ' left' }} · {{ (int) ($held[$tid] ?? 0) }} held now</span></summary>
                <form method="POST" action="{{ route('events.ticketing.pricing', [$event->id, $tid]) }}" class="eb-stack" style="gap: var(--space-4); margin-top: var(--space-3);">
                    @csrf
                    <fieldset class="eb-fieldset">
                        <legend class="eb-text-label">Price steps (dynamic pricing)</legend>
                        <p class="eb-help-text" style="margin: 0 0 var(--space-2);">For example: up to 50 tickets at 15, up to 150 at 20, up to 200 at 25. The price moves on by itself as tickets sell; a purchase that crosses a step pays each price for its share.</p>
                        @foreach($steps as $i => $step)
                            <div class="eb-grid-2">
                                <x-form-field :name="'price_steps[' . $i . '][up_to]'" :id="'st-' . $tid . '-' . $i . '-n'" type="number" :label="'Step ' . ($i + 1) . ': up to … tickets sold'" :value="$step['up_to'] ?? ''" />
                                <x-form-field :name="'price_steps[' . $i . '][price]'" :id="'st-' . $tid . '-' . $i . '-p'" type="number" :label="'Price (' . $currency . ')'" :value="$major($step['price_minor'] ?? null)" />
                            </div>
                        @endforeach
                    </fieldset>
                    <fieldset class="eb-fieldset">
                        <legend class="eb-text-label">Early-bird price</legend>
                        <div class="eb-grid-2">
                            <x-form-field name="early_bird_price" :id="'eb-' . $tid . '-p'" type="number" :label="'Early-bird price (' . $currency . ')'" :value="$major($tier['early_bird_price_minor'] ?? null)" />
                            <x-form-field name="early_bird_ends" :id="'eb-' . $tid . '-e'" type="datetime-local" label="Switches to the regular price at" :value="$local($tier['early_bird_ends_iso'] ?? null)" />
                        </div>
                    </fieldset>
                    <fieldset class="eb-fieldset">
                        <legend class="eb-text-label">Limited releases</legend>
                        <p class="eb-help-text" style="margin: 0 0 var(--space-2);">Only the tickets released so far can be sold, e.g. 20 VIP tickets now and 20 more next Friday. Leave the time empty for "now".</p>
                        @foreach($releases as $i => $release)
                            <div class="eb-grid-2">
                                <x-form-field :name="'releases[' . $i . '][quantity]'" :id="'rl-' . $tid . '-' . $i . '-q'" type="number" :label="'Release ' . ($i + 1) . ': tickets'" :value="$release['quantity'] ?? ''" />
                                <x-form-field :name="'releases[' . $i . '][opens]'" :id="'rl-' . $tid . '-' . $i . '-o'" type="datetime-local" label="Goes on sale at" :value="$local($release['opens_iso'] ?? null)" />
                            </div>
                        @endforeach
                    </fieldset>
                    <fieldset class="eb-fieldset">
                        <legend class="eb-text-label">Group prices</legend>
                        @foreach($groups as $i => $group)
                            <div class="eb-grid-2">
                                <x-form-field :name="'group_discounts[' . $i . '][min_quantity]'" :id="'gp-' . $tid . '-' . $i . '-m'" type="number" label="Buying at least … tickets" :value="$group['min_quantity'] ?? ''" />
                                <x-form-field :name="'group_discounts[' . $i . '][percent]'" :id="'gp-' . $tid . '-' . $i . '-p'" type="number" label="% off each ticket" :value="$group['percent'] ?? ''" />
                            </div>
                        @endforeach
                    </fieldset>
                    <fieldset class="eb-fieldset">
                        <legend class="eb-text-label">Bundle: food included with each ticket</legend>
                        @foreach($bundle as $i => $item)
                            <div class="eb-grid-2">
                                <x-form-field :name="'bundle_items[' . $i . '][menu_item_id]'" :id="'bd-' . $tid . '-' . $i . '-m'" type="select" label="Dish or drink" :value="$item['menu_item_id'] ?? ''" :options="['' => 'None'] + $menu->pluck('name', 'id')->all()" />
                                <x-form-field :name="'bundle_items[' . $i . '][quantity]'" :id="'bd-' . $tid . '-' . $i . '-q'" type="number" label="How many per ticket" :value="$item['quantity'] ?? 1" />
                            </div>
                        @endforeach
                        <p class="eb-help-text" style="margin: 0;">Included food is reserved from the menu's stock when the ticket is bought and collected with the ticket's QR code. Describe other VIP benefits in the ticket type itself.</p>
                    </fieldset>
                    <div><button type="submit" class="eb-btn eb-btn-primary">Save pricing for {{ $tier['name'] ?? 'this ticket' }}</button></div>
                </form>
            </details>
        @empty
            <x-empty-state title="No ticket types yet" description="Add ticket types on the event form first." illustration="tickets" />
        @endforelse
    </section>

    {{-- Discount codes --}}
    <section role="tabpanel" id="panel-codes" aria-labelledby="tab-codes" class="eb-stack" style="gap: var(--space-4);" @if($tab !== 'codes') hidden @endif>
        @foreach($codes->concat([null]) as $code)
            @php $k = $code?->id ?? 'new'; $restrict = (array) ($code?->ticket_tier_ids ?? []); @endphp
            <form method="POST" action="{{ $code ? route('events.ticketing.codes.update', [$event->id, $code->id]) : route('events.ticketing.codes.store', $event->id) }}" class="eb-panel eb-stack" style="gap: var(--space-3); margin: 0;">
                @csrf @if($code) @method('PUT') @endif
                <h2 class="eb-text-card-title" style="margin: 0;">{{ $code ? $code->code : '＋ New discount code' }}
                    @if($code)<span class="eb-help-text">{{ $code->summary($currency) }} · used {{ $code->liveUses() }}{{ $code->max_uses ? ' of ' . $code->max_uses : '' }} times</span>@endif</h2>
                <div class="eb-grid-2">
                    <x-form-field name="code" :id="'cd-' . $k . '-c'" label="Code" :value="$code?->code" :required="true" placeholder="e.g. SUMMER10" />
                    <x-form-field name="description" :id="'cd-' . $k . '-d'" label="Note (optional)" :value="$code?->description" />
                    <x-form-field name="kind" :id="'cd-' . $k . '-k'" type="select" label="Discount" :value="$code?->kind ?? 'percent'" :options="['percent' => 'Percent off', 'fixed' => 'Amount off (' . $currency . ')']" />
                    <x-form-field name="amount" :id="'cd-' . $k . '-a'" type="number" label="Percent or amount" :value="$code ? ($code->kind === 'percent' ? $code->amount : $major($code->amount)) : ''" :required="true" />
                    <x-form-field name="applies_to" :id="'cd-' . $k . '-s'" type="select" label="Applies to" :value="$code?->applies_to ?? 'tickets'" :options="['tickets' => 'Tickets', 'food' => 'Food', 'all' => 'The whole order']" />
                    <x-form-field name="min_tickets" :id="'cd-' . $k . '-m'" type="number" label="Minimum tickets (optional)" :value="$code?->min_tickets" />
                    <x-form-field name="max_uses" :id="'cd-' . $k . '-u'" type="number" label="Total uses (optional)" :value="$code?->max_uses" />
                    <x-form-field name="max_uses_per_customer" :id="'cd-' . $k . '-pc'" type="number" label="Uses per customer (optional)" :value="$code?->max_uses_per_customer" />
                    <x-form-field name="starts_at" :id="'cd-' . $k . '-st'" type="datetime-local" label="Starts (optional)" :value="$code?->starts_at?->copy()->setTimezone($tz)->format('Y-m-d\TH:i')" />
                    <x-form-field name="ends_at" :id="'cd-' . $k . '-en'" type="datetime-local" label="Expires (optional)" :value="$code?->ends_at?->copy()->setTimezone($tz)->format('Y-m-d\TH:i')" />
                    @if($affiliates->isNotEmpty())
                        <x-form-field name="affiliate_id" :id="'cd-' . $k . '-af'" type="select" label="Credit sales to promoter" :value="$code?->affiliate_id" :options="['' => 'No promoter'] + $affiliates->pluck('name', 'id')->all()" />
                    @endif
                </div>
                @if($tiers->count() > 1)
                    <fieldset class="eb-fieldset"><legend class="eb-text-label">Only for these ticket types (none ticked: all)</legend>
                        <div class="eb-cluster">@foreach($tiers as $tier)<label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="ticket_tier_ids[]" value="{{ $tier['id'] }}" @checked(in_array((string) $tier['id'], $restrict, true))><span>{{ $tier['name'] ?? 'Ticket' }}</span></label>@endforeach</div>
                    </fieldset>
                @endif
                @if($code)<input type="hidden" name="is_active_submitted" value="1"><label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="is_active" value="1" @checked($code->is_active)><span>Active</span></label>@endif
                <div class="eb-cluster">
                    <button class="eb-btn eb-btn-primary eb-btn-sm" type="submit">{{ $code ? 'Save code' : 'Create code' }}</button>
                    @if($code)<button class="eb-btn eb-btn-ghost eb-btn-sm" type="submit" form="code-del-{{ $code->id }}">Remove</button>@endif
                </div>
            </form>
            @if($code)<form id="code-del-{{ $code->id }}" method="POST" action="{{ route('events.ticketing.codes.destroy', [$event->id, $code->id]) }}" onsubmit="return confirm('Remove this code?');" hidden>@csrf @method('DELETE')</form>@endif
        @endforeach
    </section>

    {{-- Promoters --}}
    <section role="tabpanel" id="panel-promoters" aria-labelledby="tab-promoters" class="eb-stack" style="gap: var(--space-4);" @if($tab !== 'promoters') hidden @endif>
        @if($report->isNotEmpty())
            <x-data-table caption="Sales by promoter (paid orders)" :columns="['Promoter', 'Link', ['label' => 'Orders', 'numeric' => true], ['label' => 'Tickets', 'numeric' => true], ['label' => 'Revenue', 'numeric' => true], ['label' => 'Commission', 'numeric' => true]]" :empty="false">
                @foreach($report as $row)
                    <tr>
                        <td>{{ $row['affiliate']->name }} <span class="eb-help-text">({{ (float) $row['affiliate']->commission_percent }}%)</span></td>
                        <td><code>{{ route('events.show', [$event->id, 'ref' => $row['affiliate']->code]) }}</code></td>
                        <td class="eb-table-num">{{ $row['orders'] }}@if($row['pending_orders']) <span class="eb-help-text">+{{ $row['pending_orders'] }} unpaid</span>@endif</td>
                        <td class="eb-table-num">{{ $row['tickets'] }}</td>
                        <td class="eb-table-num">{{ $fmt($row['revenue_minor']) }}</td>
                        <td class="eb-table-num">{{ $fmt($row['commission_minor']) }}</td>
                    </tr>
                @endforeach
            </x-data-table>
        @endif
        @foreach($affiliates->concat([null]) as $affiliate)
            @php $k = $affiliate?->id ?? 'new'; @endphp
            <form method="POST" action="{{ $affiliate ? route('events.ticketing.promoters.update', [$event->id, $affiliate->id]) : route('events.ticketing.promoters.store', $event->id) }}" class="eb-panel eb-stack" style="gap: var(--space-3); margin: 0;">
                @csrf @if($affiliate) @method('PUT') @endif
                <h2 class="eb-text-card-title" style="margin: 0;">{{ $affiliate ? $affiliate->name : '＋ Add a promoter' }}</h2>
                <div class="eb-grid-2">
                    <x-form-field name="name" :id="'af-' . $k . '-n'" label="Name" :value="$affiliate?->name" :required="true" />
                    <x-form-field name="code" :id="'af-' . $k . '-c'" label="Link code" :value="$affiliate?->code" :required="true" help="Their link is the event page with ?ref= and this code." />
                    <x-form-field name="email" :id="'af-' . $k . '-e'" type="email" label="Email (optional)" :value="$affiliate?->email" />
                    <x-form-field name="commission_percent" :id="'af-' . $k . '-p'" type="number" label="Commission %" :value="$affiliate ? (float) $affiliate->commission_percent : ''" />
                </div>
                @if($affiliate)<input type="hidden" name="is_active_submitted" value="1"><label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="is_active" value="1" @checked($affiliate->is_active)><span>Active</span></label>@endif
                <div><button class="eb-btn eb-btn-primary eb-btn-sm" type="submit">{{ $affiliate ? 'Save promoter' : 'Add promoter' }}</button></div>
            </form>
        @endforeach
    </section>

    {{-- Complimentary --}}
    <section role="tabpanel" id="panel-complimentary" aria-labelledby="tab-complimentary" class="eb-stack" style="gap: var(--space-4);" @if($tab !== 'complimentary') hidden @endif>
        <form method="POST" action="{{ route('events.ticketing.complimentary', $event->id) }}" class="eb-panel eb-stack" style="gap: var(--space-3); margin: 0;">
            @csrf
            <h2 class="eb-text-card-title" style="margin: 0;">Issue complimentary tickets</h2>
            <p class="eb-help-text" style="margin: 0;">Free tickets for guests, press or crew. They come from the same stock as sales, go straight to the guest's EventBite wallet, and are recorded with your name and reason.</p>
            <div class="eb-grid-2">
                <x-form-field name="tier_id" id="comp-tier" type="select" label="Ticket type" :options="$tierName->all()" :required="true" />
                <x-form-field name="quantity" id="comp-qty" type="number" label="How many" value="1" :required="true" />
                <x-form-field name="email" id="comp-email" type="email" label="Guest's EventBite email" :required="true" />
                <x-form-field name="reason" id="comp-reason" label="Reason" :required="true" placeholder="e.g. Press: Island Times" />
            </div>
            <div><button class="eb-btn eb-btn-primary" type="submit" data-prevent-double-submit="true">Issue tickets</button></div>
        </form>
        @if($comps->isNotEmpty())
            <x-data-table caption="Complimentary tickets issued" :columns="['Guest', 'Tickets', 'Reason', 'Issued']" :empty="false">
                @foreach($comps as $comp)
                    <tr><td>{{ $comp->customer_name }}<br><span class="eb-help-text">{{ $comp->email }}</span></td>
                        <td>{{ $comp->items->sum('quantity') }} × {{ $comp->items->first()?->name }}</td>
                        <td>{{ $comp->complimentary_reason }}</td>
                        <td>{{ $comp->created_at->copy()->setTimezone($tz)->format('M j, g:i A') }}</td></tr>
                @endforeach
            </x-data-table>
        @endif
    </section>

    {{-- Waitlist --}}
    <section role="tabpanel" id="panel-waitlist" aria-labelledby="tab-waitlist" class="eb-stack" style="gap: var(--space-4);" @if($tab !== 'waitlist') hidden @endif>
        <div class="eb-panel" style="margin: 0;">
            <p style="margin: 0 0 var(--space-2);">When tickets come free, people waiting are offered them in the order they joined: the tickets are held for them for {{ \App\Services\Checkout\WaitlistService::offerMinutes() }} minutes and they get a notice. This runs by itself every few minutes; you can also run it now, for example after adding stock.</p>
            <form method="POST" action="{{ route('events.ticketing.waitlist.offer', $event->id) }}">@csrf<button class="eb-btn eb-btn-secondary eb-btn-sm" type="submit">Offer free tickets now</button></form>
        </div>
        <x-data-table caption="Waitlist" :columns="['Joined', 'Ticket type', ['label' => 'Tickets', 'numeric' => true], 'Status']" :empty="$waitlist->isEmpty()" empty-title="Nobody is waiting" empty-text="Buyers can join when a ticket type is sold out.">
            @foreach($waitlist as $entry)
                <tr><td>{{ $entry->created_at->copy()->setTimezone($tz)->format('M j, g:i A') }}</td><td>{{ $tierName[$entry->ticket_tier_id] ?? 'Ticket' }}</td>
                    <td class="eb-table-num">{{ $entry->quantity }}</td>
                    <td>{{ $entry->status === 'offered' ? 'Offered until ' . $entry->offer_expires_at?->copy()->setTimezone($tz)->format('g:i A') : 'Waiting' }}</td></tr>
            @endforeach
        </x-data-table>
    </section>

    {{-- Settings --}}
    <section role="tabpanel" id="panel-settings" aria-labelledby="tab-settings" @if($tab !== 'settings') hidden @endif>
        <form method="POST" action="{{ route('events.ticketing.settings', $event->id) }}" class="eb-panel eb-stack" style="gap: var(--space-3); margin: 0;">
            @csrf
            <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="allow_ticket_transfers" value="1" @checked($event->allow_ticket_transfers)>
                <span><strong>Ticket transfers.</strong> Holders of paid tickets can pass them to another EventBite account before the event; the old QR code stops working.</span></label>
            <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="waitlist_enabled" value="1" @checked($event->waitlist_enabled)>
                <span><strong>Waitlist.</strong> When a ticket type is sold out, buyers can join a waitlist and are offered tickets that come free.</span></label>
            <p class="eb-help-text" style="margin: 0;">Tickets a buyer chooses are held for {{ \App\Services\Checkout\TicketInventory::holdMinutes() }} minutes while they check out, then for the payment window once they order.</p>
            <div><button class="eb-btn eb-btn-primary" type="submit">Save settings</button></div>
        </form>
    </section>
</div>
@endsection
