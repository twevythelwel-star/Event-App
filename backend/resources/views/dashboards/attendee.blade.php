@extends('layouts.app')

@section('title', 'My Digital Tickets & Orders — EventBite')
@section('page-title', 'My Ticket Wallet')

@php
    $authUser = $user ?? Auth::user();
    $ordersList = collect($orders ?? []);
    $ordersEstimates = collect($ordersWithEstimates ?? []);
    $activePassCount = $ordersList->reject(fn ($o) => in_array($o->status, ['refunded', 'cancelled', 'rejected', 'expired'], true))->count();
    $settledPassCount = $ordersList->whereIn('status', ['paid', 'partially_refunded'])->count();
    $foodLines = collect($foodOrderLines ?? []);
    $bookedEvents = collect($upcomingBookedEvents ?? []);
    $discoverEvents = collect($upcomingDiscoverEvents ?? []);
    $discoverGroups = collect($discoverGroups ?? []);
    $notificationFeed = collect($notifications ?? []);
    $pendingStaffInvites = collect($pendingAssignments ?? []);
    $staffShifts = collect($myStaffAssignments ?? []);
    $profileItems = collect($profileChecks ?? []);
    $profilePct = (int) ($profileCompletionPercent ?? 80);
    $prefCurrency = $preferred_display_currency ?? $authUser?->preferred_display_currency ?? 'USD';
@endphp

@section('content')
@php
    $firstName = $authUser?->name ? trim(explode(' ', trim($authUser->name))[0]) : 'there';
    $featuredEvent = $discoverEvents->first();
    $remainingEvents = $discoverEvents->slice(1)->values();
    $featuredCover = $featuredEvent?->coverImage();
    $featuredPrice = $featuredEvent ? \App\Support\EventListing::fromPriceText($featuredEvent) : null;
    $featuredLocation = $featuredEvent ? \App\Support\EventListing::location($featuredEvent) : '';
@endphp
<style>
    /* Discovery-first chips: brand accent border, lime on hover/focus — same tokens as the public pages. */
    .eb-discover-chip {
        display: inline-flex; align-items: center; padding: 6px 14px; border-radius: 9999px;
        border: 1px solid var(--color-border, #26384D); background: var(--color-surface, #10243A);
        color: var(--color-text-primary, #F8FAFC); text-decoration: none; font-size: 0.85rem; font-weight: 600;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .eb-discover-chip:hover, .eb-discover-chip:focus-visible {
        border-color: var(--color-accent, #C7FF45); box-shadow: 0 0 0 3px var(--color-accent-subtle, rgba(199,255,69,.15));
    }
    /* Marketplace spotlight: full-width featured event card with image scrim. */
    .eb-spotlight {
        position: relative; display: flex; flex-direction: column; overflow: hidden;
        border-radius: var(--radius-lg, 16px); border: 1px solid var(--color-border, #26384D);
        background: var(--color-surface, #10243A); color: inherit; text-decoration: none;
        min-height: 220px;
    }
    .eb-spotlight-media { position: absolute; inset: 0; }
    .eb-spotlight-media img { width: 100%; height: 100%; object-fit: cover; }
    .eb-spotlight-scrim {
        position: absolute; inset: 0;
        background: linear-gradient(180deg, rgba(7,17,31,0.15) 0%, rgba(7,17,31,0.88) 78%);
    }
    .eb-spotlight-body {
        position: relative; margin-top: auto; padding: 1rem 1.1rem 1.1rem;
        display: flex; flex-direction: column; gap: 0.45rem; color: #F8FAFC;
    }
    .eb-spotlight-title { font-size: 1.35rem; font-weight: 800; margin: 0; color: #FFFFFF; }
    .eb-spotlight-meta { margin: 0; font-size: 0.85rem; color: #CBD5E1; }
    @media (min-width: 768px) {
        .eb-spotlight { min-height: 300px; }
        .eb-spotlight-title { font-size: 1.7rem; }
        .eb-spotlight-body { padding: 1.4rem 1.5rem 1.5rem; }
    }
</style>
<div class="eb-dashboard-shell eb-stack attendee-wallet-view">
    {{-- Unified Dashboard Header --}}
    <x-page-header
        :title="'Welcome back, ' . $firstName"
        badge="Attendee"
        description="Discover what's happening near you — food festivals, live music and community celebrations. Your tickets, orders and saved events live below.">
        <x-slot:actions>
            <div class="eb-cluster" style="gap: var(--space-2);">
                <span class="eb-badge eb-badge-neutral">
                    <span>Currency: {{ $prefCurrency }}</span>
                </span>
                <x-button variant="secondary" size="sm" icon="settings" :href="route('settings.show') . '#currency'">Settings</x-button>
                <x-button variant="primary" size="sm" icon="events" :href="route('events.index')">Browse events</x-button>
            </div>
        </x-slot:actions>
    </x-page-header>

    {{-- Unified Quick-Jump Section Pill Navigation --}}
    <nav class="eb-dashboard-nav-pills" aria-label="Dashboard sections">
        <a href="#discover" class="eb-dashboard-nav-pill">
            <x-icon name="events" size="sm" />
            <span>Discover</span>
        </a>
        <a href="#passes" class="eb-dashboard-nav-pill">
            <x-icon name="qr" size="sm" />
            <span>Ticket Collection</span>
            <span class="eb-dashboard-nav-pill-count">{{ $ordersList->count() }}</span>
        </a>
        <a href="#upcoming-events" class="eb-dashboard-nav-pill">
            <x-icon name="calendar" size="sm" />
            <span>Upcoming Events</span>
            <span class="eb-dashboard-nav-pill-count">{{ $bookedEvents->count() }}</span>
        </a>
        <a href="#food-orders" class="eb-dashboard-nav-pill">
            <x-icon name="utensils" size="sm" />
            <span>Food Orders</span>
            <span class="eb-dashboard-nav-pill-count">{{ $foodLines->count() }}</span>
        </a>
        <a href="#orders" class="eb-dashboard-nav-pill">
            <x-icon name="receipt" size="sm" />
            <span>Purchase History</span>
            <span class="eb-dashboard-nav-pill-count">{{ $ordersList->count() }}</span>
        </a>
        <a href="#notifications" class="eb-dashboard-nav-pill">
            <x-icon name="bell" size="sm" />
            <span>Notifications</span>
            <span class="eb-dashboard-nav-pill-count">{{ $notificationFeed->count() + $pendingStaffInvites->count() }}</span>
        </a>
        <a href="#profile-completion" class="eb-dashboard-nav-pill">
            <x-icon name="users" size="sm" />
            <span>Profile ({{ $profilePct }}%)</span>
        </a>
        <a href="#staff-access" class="eb-dashboard-nav-pill">
            <x-icon name="checkin" size="sm" />
            <span>Staff Access</span>
        </a>
    </nav>

    {{-- Unified KPI Row --}}
    <section class="eb-grid-4" aria-label="Wallet summary">
        <x-metric-card
            label="Active passes"
            :value="$activePassCount"
            :note="$settledPassCount . ' verified for gate entry'"
            href="#passes" />
        <x-metric-card
            label="Upcoming events"
            :value="$bookedEvents->count()"
            :note="$discoverEvents->count() . ' more events open for booking'"
            href="#upcoming-events" />
        <x-metric-card
            label="Food items ordered"
            :value="(int) $foodLines->sum(fn ($r) => (int) ($r['item']->quantity ?? 0))"
            :note="(int) $foodLines->sum(fn ($r) => (int) ($r['item']->collected_quantity ?? 0)) . ' portions collected'"
            href="#food-orders" />
        <x-metric-card
            label="Profile completion"
            :value="$profilePct . '%'"
            :note="'Preferred currency: ' . $prefCurrency"
            href="#profile-completion" />
    </section>

    {{-- Event Host Status & Privileges Banner --}}
    @if($authUser && $authUser->hasRole(\App\Enums\AccountType::EVENT_HOST))
        <section class="eb-card eb-panel" aria-label="Event Host privileges" style="border-color: rgba(52, 211, 153, 0.35);">
            <div class="eb-cluster" style="justify-content: space-between; align-items: center; gap: 1rem;">
                <div>
                    <span class="eb-badge eb-badge-success">Approved Event Host</span>
                    <h2 class="eb-text-card-title" style="margin: 0.35rem 0 0.15rem;">Host Command Center Available</h2>
                    <p class="eb-text-supporting" style="margin: 0;">You hold active Event Host privileges to create events, upload flyers, and manage ticketing and food operations.</p>
                </div>
                <form action="{{ route('context.switch') }}" method="POST" style="margin: 0;">
                    @csrf
                    <input type="hidden" name="target_role" value="event_host">
                    <button type="submit" class="eb-btn eb-btn-primary">
                        <span>Switch to Host Dashboard</span>
                        <x-icon name="arrow-right" size="sm" />
                    </button>
                </form>
            </div>
        </section>
    @elseif(!empty($hostApplication) && $hostApplication->isPending())
        <section class="eb-card eb-panel" aria-label="Host application status" style="border-color: rgba(251, 191, 36, 0.35);">
            <div class="eb-cluster" style="justify-content: space-between; align-items: center; gap: 1rem;">
                <div>
                    <span class="eb-badge eb-badge-warning">Application Under Review</span>
                    <h2 class="eb-text-card-title" style="margin: 0.35rem 0 0.15rem;">Event Host Request for "{{ $hostApplication->organization_name }}"</h2>
                    <p class="eb-text-supporting" style="margin: 0;">Submitted on {{ $hostApplication->created_at->format('M j, Y') }}. Our operations desk is reviewing your request.</p>
                </div>
            </div>
        </section>
    @elseif($authUser && !$authUser->hasRole(\App\Enums\AccountType::EVENT_HOST))
        <section class="eb-card eb-panel" aria-label="Apply for Event Host privileges">
            <div class="eb-cluster" style="justify-content: space-between; align-items: center; gap: 1rem;">
                <div>
                    <h2 class="eb-text-card-title" style="margin: 0 0 0.2rem;">Want to organize events on EventBite?</h2>
                    <p class="eb-text-supporting" style="margin: 0;">Apply for Event Host privileges to publish flyers, sell digital tickets, and accept advance food orders.</p>
                </div>
                <a href="{{ route('host.apply') }}" class="eb-btn eb-btn-secondary">Apply for Host Privileges</a>
            </div>
        </section>
    @endif

    {{-- 0. Discover events first: what attendees come here to do --}}
    <section id="discover" class="eb-stack" aria-labelledby="discover-heading">
        <div class="eb-section-header">
            <div>
                <h2 id="discover-heading" class="eb-text-section-title" style="margin: 0;">Discover your next event</h2>
                <p class="eb-text-supporting" style="margin: 0;">Food festivals, live music and community celebrations — tickets and food ordering in one place.</p>
            </div>
            <x-button variant="primary" size="sm" icon="events" :href="route('events.index')">Browse all events</x-button>
        </div>

        <form method="GET" action="{{ route('events.index') }}" class="eb-cluster" role="search" aria-label="Search events" style="gap: var(--space-2); flex-wrap: nowrap;">
            <div class="eb-form-group" style="flex: 1 1 auto; margin: 0; min-width: 0;">
                <label class="sr-only" for="discover-search-q">Search events</label>
                <input type="search" id="discover-search-q" name="q" class="eb-input" placeholder="Search events, artists, food…" autocomplete="off">
            </div>
            <div class="eb-form-group" style="flex: 0 1 9rem; margin: 0; min-width: 0;">
                <label class="sr-only" for="discover-search-city">City or state</label>
                <input type="text" id="discover-search-city" name="city" class="eb-input" placeholder="City or state" autocomplete="off">
            </div>
            <button type="submit" class="eb-btn eb-btn-primary" style="flex-shrink: 0;">
                <x-icon name="search" size="sm" />
                <span>Search</span>
            </button>
        </form>

        @if($discoverGroups->isNotEmpty())
            <div class="eb-cluster" style="gap: var(--space-2); flex-wrap: wrap;" aria-label="Browse by kind of event">
                @foreach($discoverGroups as $group)
                    <a href="{{ route('events.index', ['kind' => $group['key']]) }}" class="eb-discover-chip">{{ $group['label'] }}</a>
                @endforeach
                <a href="{{ route('events.index', ['q' => 'family']) }}" class="eb-discover-chip">Family-friendly</a>
            </div>
        @endif

        {{-- Featured spotlight: the soonest upcoming event gets the marketplace hero treatment --}}
        @if($featuredEvent)
            <a href="{{ route('events.show', $featuredEvent->id) }}" class="eb-spotlight" aria-label="Featured event: {{ $featuredEvent->title }}">
                <div class="eb-spotlight-media">
                    <x-responsive-image :image="$featuredCover" alt="" sizes="(min-width: 1024px) 1100px, 100vw" />
                    <div class="eb-spotlight-scrim" aria-hidden="true"></div>
                </div>
                <div class="eb-spotlight-body">
                    <span class="eb-badge eb-badge-info">Featured this week</span>
                    <h3 class="eb-spotlight-title eb-clamp-2">{{ $featuredEvent->title }}</h3>
                    <p class="eb-spotlight-meta">
                        {{ $featuredEvent->date_str }}@if($featuredEvent->time_str) · {{ $featuredEvent->time_str }}@endif
                        @if($featuredLocation !== '') · {{ \Illuminate\Support\Str::limit($featuredLocation, 60) }}@endif
                    </p>
                    <div class="eb-cluster" style="justify-content: space-between; align-items: center; gap: var(--space-3);">
                        <span class="eb-text-price">{{ $featuredPrice ?? 'Tickets not on sale' }}</span>
                        <span class="eb-event-card-cta">View event <span aria-hidden="true">→</span></span>
                    </div>
                </div>
            </a>
        @endif

        @if($remainingEvents->isNotEmpty())
            <div class="eb-grid-4">
                @foreach($remainingEvents as $event)
                    <x-event-card :event="$event" />
                @endforeach
            </div>
        @else
            <x-empty-state
                icon="events"
                illustration="events"
                title="No upcoming events to discover"
                description="New events are published by hosts across the country. Check back soon.">
                <x-button variant="primary" icon="events" :href="route('events.index')">Explore the catalog</x-button>
            </x-empty-state>
        @endif
    </section>

    {{-- 1. Ticket Collection Visuals --}}
    <section id="passes" class="eb-stack" aria-labelledby="passes-heading">
        <div class="eb-section-header">
            <div>
                <h2 id="passes-heading" class="eb-text-section-title" style="margin: 0;">Active QR Event &amp; Food Passes</h2>
                <p class="eb-text-supporting" style="margin: 0;">Present your digital pass to the Gate Checker and Food Station on event day. Open any pass for its scannable admission QR code.</p>
            </div>
            <div class="eb-cluster">
                <span class="eb-badge eb-badge-success">{{ $ordersList->count() }} Passes Active</span>
                <x-button variant="secondary" icon="tickets" :href="route('tickets.collection')">My Tickets</x-button>
            </div>
        </div>

        @if($ordersList->isNotEmpty())
            <div class="eb-grid-2">
                @foreach($ordersList as $order)
                    <x-ticket-card :order="$order" :href="route('tickets.show', $order)" />
                @endforeach
            </div>
        @else
            <x-empty-state
                icon="tickets"
                illustration="tickets"
                title="No ticket passes found"
                description="You do not currently have any active event passes or advance meal orders under this account.">
                <x-button variant="primary" icon="events" :href="route('events.index')">Explore upcoming events</x-button>
            </x-empty-state>
        @endif
    </section>

    {{-- 2. Upcoming events on the attendee's passes --}}
    <section id="upcoming-events" class="eb-stack" aria-labelledby="upcoming-events-heading">
        <div class="eb-section-header">
            <div>
                <h2 id="upcoming-events-heading" class="eb-text-section-title" style="margin: 0;">Your upcoming events</h2>
                <p class="eb-text-supporting" style="margin: 0;">Events on your calendar, with tickets and food ordering ready when you need them.</p>
            </div>
            <x-button variant="ghost" size="sm" :href="route('events.index')">View all events</x-button>
        </div>

        @if($bookedEvents->isNotEmpty())
            <div class="eb-grid-2">
                @foreach($bookedEvents as $event)
                    @php
                        $matchingOrder = $ordersList->firstWhere('event_id', $event->id);
                    @endphp
                    <article class="eb-assigned-event-banner">
                        <div class="eb-assigned-event-thumb">
                            <x-responsive-image :image="$event->coverImage()" alt="" sizes="72px" style="width: 100%; height: 100%; object-fit: cover;" />
                        </div>
                        @php $keepsake = $event->visibleGallery()->first(); @endphp
                        @if($keepsake)
                            <div class="eb-assigned-event-thumb" aria-hidden="true">
                                <x-responsive-image :image="$keepsake->responsiveImage()" alt="" sizes="72px" style="width: 100%; height: 100%; object-fit: cover;" />
                            </div>
                        @endif
                        <div style="min-width: 0;">
                            <div class="eb-cluster" style="gap: 0.4rem; margin-bottom: 0.25rem;">
                                <span class="eb-badge eb-badge-success">Booked on your pass</span>
                                @if($event->category)<span class="eb-badge eb-badge-neutral">{{ $event->category }}</span>@endif
                            </div>
                            <h3 class="eb-text-card-title" style="margin: 0 0 0.2rem;">
                                <a href="{{ route('events.show', $event->id) }}" style="color: var(--color-text-primary); text-decoration: none;">{{ $event->title }}</a>
                            </h3>
                            <p class="eb-help-text" style="margin: 0;">
                                {{ $event->date_str ?: 'Date TBA' }}{{ $event->time_str ? ' · ' . $event->time_str : '' }}
                                &bull; {{ $event->venue_name ?: 'Venue TBA' }}{{ $event->city ? ', ' . $event->city : '' }}
                            </p>
                        </div>
                        <div class="eb-cluster" style="gap: 0.5rem;">
                            @if($matchingOrder)
                                <a href="{{ route('tickets.show', $matchingOrder) }}" class="eb-btn eb-btn-primary eb-btn-sm">Open pass</a>
                            @endif
                            <a href="{{ route('events.show', $event->id) }}" class="eb-btn eb-btn-secondary eb-btn-sm">Event page</a>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <x-empty-state icon="calendar" title="No events booked yet" description="Discover events above and reserve your tickets and meals." />
        @endif
    </section>

    {{-- 3. Food Orders --}}
    <section id="food-orders" class="eb-stack" aria-labelledby="food-orders-heading">
        <div class="eb-section-header">
            <div>
                <h2 id="food-orders-heading" class="eb-text-section-title" style="margin: 0;">Food orders &amp; collection vouchers</h2>
                <p class="eb-text-supporting" style="margin: 0;">Advance meal items linked to your passes, pickup numbers, and live station handover progress.</p>
            </div>
        </div>

        <x-data-table
            caption="Advance food orders"
            :columns="['Dish / Item', 'Event', 'Pass & Pickup #', ['label' => 'Ordered', 'numeric' => true], ['label' => 'Collected', 'numeric' => true], 'Status', ['label' => 'Voucher', 'numeric' => true]]"
            :empty="$foodLines->isEmpty()"
            empty-title="No advance food orders yet"
            empty-text="Pre-ordered meals and combo packages from event menus appear here with their pickup numbers.">
            @foreach($foodLines as $row)
                @php
                    /** @var \App\Models\Order $fOrder */
                    $fOrder = $row['order'];
                    /** @var \App\Models\OrderItem $fItem */
                    $fItem = $row['item'];
                    $remaining = max(0, (int) $fItem->quantity - (int) $fItem->collected_quantity);
                    $collectedAll = $remaining === 0 && (int) $fItem->quantity > 0;
                    $optionsSummary = is_array($fItem->selected_options_summary) ? implode(', ', array_filter($fItem->selected_options_summary)) : (string) ($fItem->selected_options_summary ?? '');
                @endphp
                <tr>
                    <td>
                        <div style="font-weight: 700; color: var(--color-text-primary);">{{ $fItem->name }}</div>
                        @if($optionsSummary !== '')
                            <div class="eb-help-text">{{ $optionsSummary }}</div>
                        @endif
                    </td>
                    <td>{{ $row['event_title'] }}</td>
                    <td>
                        <div style="font-family: var(--font-mono); font-weight: 700; color: var(--color-accent);">{{ $row['pickup_number'] }}</div>
                        <div class="eb-help-text">Pass: {{ $fOrder->ticket_code }}</div>
                    </td>
                    <td class="eb-table-num">{{ (int) $fItem->quantity }}</td>
                    <td class="eb-table-num">{{ (int) $fItem->collected_quantity }} / {{ (int) $fItem->quantity }}</td>
                    <td>
                        <span class="eb-badge {{ $collectedAll ? 'eb-badge-success' : ((int) $fItem->collected_quantity > 0 ? 'eb-badge-info' : 'eb-badge-warning') }}">
                            {{ $collectedAll ? 'Collected' : ((int) $fItem->collected_quantity > 0 ? 'Partly collected' : (\App\Models\OrderItem::PREPARATION_STATUSES[$fItem->preparation_status ?? 'pending'] ?? 'Pending pickup')) }}
                        </span>
                    </td>
                    <td class="eb-table-num">
                        <a href="{{ route('tickets.show', $fOrder->id) }}" class="eb-btn eb-btn-secondary eb-btn-sm">View voucher</a>
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </section>

    {{-- 4. Purchase History & Financial Receipts --}}
    <section id="orders" class="eb-stack" aria-labelledby="orders-heading">
        <div class="eb-section-header">
            <div>
                <h2 id="orders-heading" class="eb-text-section-title" style="margin: 0;">Order History &amp; Financial Receipts</h2>
                <p class="eb-text-supporting" style="margin: 0;">Official records for ticket purchases, food pre-orders, payment status, and cancellation requests.</p>
            </div>
        </div>

        <x-data-table
            caption="Order history and receipts"
            :columns="['Order Reference', 'Date', 'Payment Status', 'Fulfillment', ['label' => 'Amount (Charged)', 'numeric' => true], ['label' => 'Estimate (' . $prefCurrency . ')', 'numeric' => true], ['label' => 'Receipt', 'numeric' => true]]"
            :empty="$ordersList->isEmpty()"
            empty-title="No orders on record"
            empty-text="Your past orders and digital receipts will appear here once placed.">
            @foreach($ordersList as $order)
                <tr>
                    <td>
                        <div style="font-weight: 700; color: var(--color-text-primary); font-family: var(--font-mono);">#{{ $order->order_number }}</div>
                        <span class="eb-help-text">Code: {{ $order->ticket_code }}</span>
                        @php($cancellation = $ordersEstimates->firstWhere('id', $order->id)['cancellation'] ?? [])
                        <div class="eb-help-text" style="margin-top: 0.3rem;">
                            Refund: {{ str_replace('_', ' ', $cancellation['refundStatus'] ?? 'not_started') }}
                            @if(!empty($cancellation['deadline'])) · Requests close {{ \Illuminate\Support\Carbon::parse($cancellation['deadline'])->format('M d, Y g:i A T') }} @endif
                        </div>
                        @if(!empty($cancellation['policyNotes']))<div class="eb-help-text" style="margin-top: 0.15rem;">{{ $cancellation['policyNotes'] }}</div>@endif
                        @foreach(($cancellation['refundHistory'] ?? []) as $refund)
                            <div class="eb-help-text" style="margin-top: 0.15rem;">Refund {{ \App\Support\Money::formatMinor((int) $refund['amount_minor'], $order->currency) }} · {{ $refund['refundedAt'] }} · {{ $refund['reason'] }}</div>
                        @endforeach
                        @if(!empty($cancellation['eligible']))
                            <form method="POST" action="{{ route('attendee.cancellations.store', $order->id) }}" class="eb-cluster" style="margin-top: 0.45rem; gap: 0.4rem;">
                                @csrf
                                <input class="eb-input" style="max-width: 220px; min-height: 34px; padding: 0.35rem 0.65rem;" name="reason" maxlength="500" placeholder="Reason (optional)" aria-label="Cancellation reason">
                                <button class="eb-btn eb-btn-secondary eb-btn-sm" type="submit">Request cancellation</button>
                            </form>
                        @elseif(!empty($cancellation['status']))
                            <div class="eb-help-text" style="margin-top: 0.25rem;">Request status: {{ str_replace('_', ' ', $cancellation['status']) }}</div>
                        @endif
                    </td>
                    <td>{{ date('M d, Y', strtotime((string) $order->created_at)) }}</td>
                    <td>@include('components.status-indicator', ['status' => $order->status])</td>
                    <td>
                        <span class="eb-badge {{ $order->all_handed_over ? 'eb-badge-success' : 'eb-badge-warning' }}">
                            {{ $order->all_handed_over ? 'Collected' : 'Pending' }}
                        </span>
                    </td>
                    <td class="eb-table-num">
                        <strong class="eb-text-price">{{ \App\Support\Money::format($order->total_amount, $order->currency) }}</strong>
                    </td>
                    <td class="eb-table-num">
                        <span class="eb-help-text">
                            {{ $order->currency === $prefCurrency ? '—' : '~' . ($ordersEstimates->firstWhere('id', $order->id)['display_estimate'] ?? '') }}
                        </span>
                    </td>
                    <td class="eb-table-num">
                        <a href="{{ route('tickets.show', $order->id) }}" class="eb-btn eb-btn-secondary eb-btn-sm">
                            View<span class="sr-only"> order {{ $order->order_number }}</span>
                        </a>
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </section>

    {{-- 5. Notifications & 6. Profile Completion (Unified 2-Column Grid) --}}
    <div class="eb-grid-2">
        {{-- Notifications --}}
        <section id="notifications" class="eb-panel card" style="margin: 0;" aria-labelledby="notifications-heading">
            <div class="eb-card-header card-header">
                <div>
                    <h2 id="notifications-heading" class="eb-card-title card-title">Notifications &amp; activity</h2>
                    <p class="eb-card-subtitle card-subtitle">Order confirmations, payment verifications, and staff assignment alerts.</p>
                </div>
                <span class="eb-badge eb-badge-neutral">{{ $notificationFeed->count() + $pendingStaffInvites->count() }}</span>
            </div>

            @if($notificationFeed->isEmpty() && $pendingStaffInvites->isEmpty())
                <x-empty-state icon="bell" title="No recent notifications" description="Order receipts, gate check-in confirmations, and staff invitations appear here." />
            @else
                <ul class="eb-notification-list">
                    @foreach($pendingStaffInvites as $invite)
                        <li class="eb-notification-item">
                            <div class="eb-notification-item-main">
                                <span class="eb-notification-icon"><x-icon name="staff" size="sm" /></span>
                                <div>
                                    <div style="font-weight: 700; color: var(--color-text-primary);">Staff Assignment Offer: {{ $invite->worker_role->label() }}</div>
                                    <div class="eb-help-text">{{ $invite->event?->title ?? 'Event' }} · Station: {{ str_replace('_', ' ', $invite->station_assignment ?? 'all') }}</div>
                                </div>
                            </div>
                            <a href="#staff-access" class="eb-btn eb-btn-primary eb-btn-sm">Respond</a>
                        </li>
                    @endforeach
                    @foreach($notificationFeed as $item)
                        <li class="eb-notification-item">
                            <div class="eb-notification-item-main">
                                <span class="eb-notification-icon"><x-icon name="bell" size="sm" /></span>
                                <div>
                                    <div style="font-weight: 700; color: var(--color-text-primary);">{{ $item->title ?: $item->email_subject ?: ucfirst(str_replace('_', ' ', (string) $item->trigger)) }}</div>
                                    <div class="eb-help-text">
                                        @if($item->order_number) Order #{{ $item->order_number }} · @endif
                                        {{ \Illuminate\Support\Str::limit((string) $item->message, 110) }}
                                    </div>
                                </div>
                            </div>
                            <span class="eb-help-text" style="white-space: nowrap;">{{ $item->created_at?->diffForHumans() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Profile Completion --}}
        <section id="profile-completion" class="eb-panel card" style="margin: 0;" aria-labelledby="profile-completion-heading">
            <div class="eb-card-header card-header">
                <div>
                    <h2 id="profile-completion-heading" class="eb-card-title card-title">Profile completion</h2>
                    <p class="eb-card-subtitle card-subtitle">Keep your contact details and display currency up to date for seamless gate entry and order alerts.</p>
                </div>
                <span class="eb-badge {{ $profilePct === 100 ? 'eb-badge-success' : 'eb-badge-info' }}">{{ $profilePct }}% Complete</span>
            </div>

            <div class="eb-chart-row" style="margin-bottom: 0.75rem;">
                <div class="eb-chart-row-head">
                    <span class="eb-chart-row-title">Account readiness</span>
                    <span class="eb-chart-row-meta">{{ $profileItems->where('done', true)->count() }} of {{ $profileItems->count() }} complete</span>
                </div>
                <div class="eb-progress-track" role="progressbar" aria-valuenow="{{ $profilePct }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="eb-progress-fill" style="width: {{ $profilePct }}%;"></div>
                </div>
            </div>

            <div class="eb-profile-checklist">
                @foreach($profileItems as $check)
                    <a href="{{ $check['href'] }}" class="eb-profile-check-item {{ $check['done'] ? 'is-complete' : 'is-pending' }}" style="text-decoration: none; color: inherit;">
                        <div>
                            <div style="font-weight: 700; color: var(--color-text-primary);">{{ $check['label'] }}</div>
                            <div class="eb-help-text">{{ $check['detail'] }}</div>
                        </div>
                        <span class="eb-badge {{ $check['done'] ? 'eb-badge-success' : 'eb-badge-warning' }}">
                            {{ $check['done'] ? '✓ Done' : 'Update' }}
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    </div>

    {{-- Event Staff Access & One-Time Claim Code Section --}}
    <section class="eb-card eb-panel" id="staff-access" aria-labelledby="staff-access-heading">
        <div class="eb-card-header card-header">
            <div>
                <div class="eb-cluster" style="gap: 0.5rem; margin-bottom: 0.25rem;">
                    <span class="eb-badge eb-badge-purple">Event Staff Access</span>
                    <span class="eb-help-text">Privacy-Preserving Staff Assignment</span>
                </div>
                <h2 id="staff-access-heading" class="eb-card-title card-title">Event Worker Positions &amp; Assignment Codes</h2>
                <p class="eb-card-subtitle card-subtitle">Generate a temporary, single-use 10-minute code to allow an Event Host to assign your account as an event worker.</p>
            </div>
            @if($pendingStaffInvites->isNotEmpty())
                <span class="eb-badge eb-badge-warning">{{ $pendingStaffInvites->count() }} Action Required</span>
            @endif
        </div>

        @if($pendingStaffInvites->isNotEmpty())
            <div class="eb-stack" style="gap: 0.75rem; margin-bottom: 1.25rem;">
                <h3 class="eb-text-label">Pending Staff Assignment Offers</h3>
                @foreach($pendingStaffInvites as $invitation)
                    <div class="eb-card" style="padding: 1rem;">
                        <div class="eb-cluster" style="justify-content: space-between; align-items: flex-start; gap: 0.75rem;">
                            <div>
                                <div style="font-weight: 800; font-size: 1rem; color: var(--color-text-primary);">{{ $invitation->event?->title ?? 'Event' }}</div>
                                <div class="eb-help-text" style="margin-top: 0.2rem;">
                                    Position: <strong style="color: var(--color-accent);">{{ $invitation->worker_role->label() }}</strong>
                                    &bull; Station: {{ str_replace('_', ' ', $invitation->station_assignment ?? 'all') }}
                                    &bull; Host: {{ $invitation->assignedBy?->name ?? 'Event Host' }}
                                </div>
                                <div class="eb-help-text" style="margin-top: 0.15rem;">
                                    Shift: {{ $invitation->starts_at ? $invitation->starts_at->format('M j, Y g:i A') : 'Immediate' }} &mdash;
                                    {{ $invitation->expires_at ? $invitation->expires_at->format('M j, Y g:i A') : 'No Expiry' }} ({{ $invitation->timezone ?? 'UTC' }})
                                </div>
                            </div>
                            <div class="eb-cluster" style="gap: 0.5rem;">
                                <form action="{{ route('staff-assignments.accept', $invitation->id) }}" method="POST" style="margin: 0;">
                                    @csrf
                                    <button type="submit" class="eb-btn eb-btn-primary eb-btn-sm">Accept Assignment</button>
                                </form>
                                <form action="{{ route('staff-assignments.decline', $invitation->id) }}" method="POST" onsubmit="return confirm('Decline this staff assignment offer?');" style="margin: 0;">
                                    @csrf
                                    <button type="submit" class="eb-btn eb-btn-destructive eb-btn-sm">Decline</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if (session('claim_code_generated') && session('plain_code'))
            <div class="eb-ticket-serial-plate" style="justify-content: center; text-align: center; flex-direction: column; margin-bottom: 1.25rem;">
                <span class="eb-badge eb-badge-success">Single-Use Staff Claim Code</span>
                <div class="eb-ticket-serial-code" style="font-size: 1.85rem; margin: 0.35rem 0;">
                    {{ session('plain_code') }}
                </div>
                <p class="eb-help-text" style="margin: 0 0 0.75rem;">
                    Share this code with your Event Host. Valid for <strong>10 minutes</strong> (until {{ session('code_expires_at') }}). Single-use only.
                </p>
                <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" onclick="navigator.clipboard.writeText('{{ session('plain_code') }}'); this.textContent = 'Copied ✓';">
                    Copy Code
                </button>
            </div>
        @else
            <div class="eb-cluster" style="justify-content: space-between; align-items: center; gap: 1rem; padding: 1.15rem; border-radius: 14px; background: rgba(7, 15, 28, 0.68); border: 1px solid rgba(139, 179, 255, 0.16); margin-bottom: 1rem;">
                <div style="max-width: 600px;">
                    <div style="font-weight: 700; color: var(--color-text-primary); font-size: 0.95rem;">Working as staff at an upcoming event?</div>
                    <p class="eb-help-text" style="margin: 0.25rem 0 0;">
                        Generate an expiring 10-minute code. The Event Host will enter it to verify your account without accessing your private profile or email directory.
                    </p>
                </div>
                <form action="{{ route('staff-codes.generate') }}" method="POST" style="margin: 0;">
                    @csrf
                    <button type="submit" class="eb-btn eb-btn-primary">Generate Staff Claim Code</button>
                </form>
            </div>
        @endif

        @if($staffShifts->isNotEmpty())
            <div class="eb-stack" style="gap: 0.5rem;">
                <span class="eb-text-label">My Event Staff Assignments</span>
                <x-data-table
                    caption="My event staff assignments"
                    :columns="['Event', 'Position', 'Schedule', 'Status', ['label' => 'Action', 'numeric' => true]]"
                    :empty="false">
                    @foreach($staffShifts as $assignment)
                        <tr>
                            <td><strong style="color: var(--color-text-primary);">{{ $assignment->event?->title ?? 'Assigned Event' }}</strong></td>
                            <td><span class="eb-badge eb-badge-purple">{{ $assignment->worker_role->label() }}</span></td>
                            <td>
                                <span class="eb-help-text">
                                    {{ $assignment->starts_at ? $assignment->starts_at->format('M j, g:i A') : 'Active' }} &mdash;
                                    {{ $assignment->expires_at ? $assignment->expires_at->format('M j, g:i A') : 'No Expiry' }}
                                </span>
                            </td>
                            <td>
                                @if ($assignment->isCurrentlyActive())
                                    <span class="eb-badge eb-badge-success">Active Shift</span>
                                @elseif ($assignment->isScheduled())
                                    <span class="eb-badge eb-badge-info">Scheduled</span>
                                @elseif ($assignment->isExpired())
                                    <span class="eb-badge eb-badge-neutral">Expired</span>
                                @else
                                    <span class="eb-badge eb-badge-warning">{{ ucfirst($assignment->status) }}</span>
                                @endif
                            </td>
                            <td class="eb-table-num">
                                @if ($assignment->isCurrentlyActive() && $assignment->event)
                                    <a href="{{ route('worker.terminal', $assignment->event->id) }}" class="eb-btn eb-btn-secondary eb-btn-sm">
                                        Open Terminal
                                    </a>
                                @else
                                    <span class="eb-help-text">&mdash;</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-data-table>
            </div>
        @endif
    </section>
</div>
@endsection
