@extends('layouts.app')

@section('title', ($event->title ?? 'Event') . ' — Island Events')
@section('page-title', $event->title ?? 'Event Details')

@section('content')
<div class="space-y-6">
    <!-- Back Navigation -->
    <div>
        <a href="{{ route('events.index') }}" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 0.35rem;">
            <span>&larr;</span>
            <span>Back to All Events</span>
        </a>
    </div>

    <!-- Event Hero Banner -->
    <div class="card" style="padding: 0; overflow: hidden; border: none; box-shadow: var(--shadow-md);">
        <div style="height: 280px; background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); position: relative; display: flex; align-items: center; justify-content: center;">
            @if(!empty($event->flyer_url))
                <img src="{{ $event->flyer_url }}" alt="{{ $event->title }}" style="width: 100%; height: 100%; object-fit: cover;">
            @else
                <div style="text-align: center; color: var(--color-white); opacity: 0.9;">
                    <span style="font-size: 4rem;">🌴</span>
                    <div style="font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; margin-top: 0.5rem;">Island Event Experience</div>
                </div>
            @endif

            <div style="position: absolute; bottom: 16px; left: 24px; right: 24px; background: rgba(16, 24, 40, 0.75); backdrop-filter: blur(8px); padding: 1rem 1.5rem; border-radius: var(--radius-md); border: 1px solid rgba(255, 255, 255, 0.15); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="font-size: 1.45rem; font-weight: 800; color: var(--color-white); margin-bottom: 0.2rem;">
                        {{ $event->title }}
                    </h1>
                    <div style="font-size: 0.85rem; color: #cbd5e1; display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap;">
                        <span>📅 {{ $event->date ? (is_string($event->date) ? date('l, F j, Y', strtotime($event->date)) : $event->date->format('l, F j, Y')) : 'Date TBA' }}</span>
                        <span>📍 {{ $event->venue ?? 'United States' }}</span>
                    </div>
                </div>

                <div>
                    @if($event->is_ordering_open)
                        <span class="badge badge-active">Kitchen Accepting Pre-Orders</span>
                    @else
                        <span class="badge" style="background: #e2e8f0; color: #475569;">Ordering Closed</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Two-Column Layout: Details + Menu Items -->
    <div class="grid-2">
        <!-- Event Overview & Instructions -->
        <div class="card space-y-4">
            <h2 style="font-size: 1.15rem; font-weight: 700; color: var(--color-navy); border-bottom: 1px solid var(--color-border); padding-bottom: 0.5rem;">
                About This Event
            </h2>
            <p style="font-size: 0.88rem; color: var(--color-text-secondary); line-height: 1.6;">
                {{ $event->description ?? 'Join us for this special Caribbean food and entertainment event. All food packages are prepared fresh on-site and redeemed with instant digital QR passes.' }}
            </p>

            <div style="background: var(--color-bg-page); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 1rem; font-size: 0.82rem; color: var(--color-text-secondary); space-y: 1;">
                <div style="font-weight: 700; color: var(--color-navy); margin-bottom: 0.25rem;">Food Collection Protocol:</div>
                <div>&bull; Present your QR ticket pass at the designated food station.</div>
                <div>&bull; Orders are verified in real-time by island staff.</div>
                <div>&bull; Bank transfers & manual payments must be verified prior to event entry.</div>
            </div>

            <!-- Authentication CTA Box -->
            @guest
            <div style="background: var(--color-indigo-soft); border: 1px solid var(--color-indigo-subtle); border-radius: var(--radius-md); padding: 1.25rem; display: flex; flex-direction: column; gap: 0.75rem;">
                <div style="font-size: 0.92rem; font-weight: 700; color: var(--color-navy); display: flex; align-items: center; gap: 0.4rem;">
                    <span>🔒</span>
                    <span>Sign In Required for Orders & Tickets</span>
                </div>
                <p style="font-size: 0.82rem; color: var(--color-text-secondary); line-height: 1.5;">
                    To protect order integrity and issue personal QR tickets, you must be signed in with an attendee account.
                </p>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <a href="{{ route('login') }}" class="btn btn-secondary btn-sm" style="flex: 1; text-align: center;">Log In</a>
                    <a href="{{ route('register') }}" class="btn btn-primary btn-sm" style="flex: 1; text-align: center;">Create Account</a>
                </div>
            </div>
            @endguest
        </div>

        <!-- Menu Offerings Preview -->
        <div class="card space-y-4">
            <h2 style="font-size: 1.15rem; font-weight: 700; color: var(--color-navy); border-bottom: 1px solid var(--color-border); padding-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
                <span>Featured Caribbean Menu</span>
                <span style="font-size: 0.75rem; color: var(--color-text-muted); font-weight: 500;">Pre-order in advance</span>
            </h2>

            @if(empty($event->menuItems) || count($event->menuItems) === 0)
                <div class="empty-state" style="padding: 2rem 1rem;">
                    <div class="empty-state-icon">🍲</div>
                    <div class="empty-state-title">Menu Coming Soon</div>
                    <div class="empty-state-desc">The host is preparing seasonal dishes and pricing tiers.</div>
                </div>
            @else
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    @foreach($event->menuItems as $item)
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.85rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-bg-page);">
                            <div>
                                <div style="font-weight: 700; font-size: 0.92rem; color: var(--color-navy);">{{ $item->name }}</div>
                                <div style="font-size: 0.78rem; color: var(--color-text-secondary);">{{ $item->description ?? 'Authentic Island dish' }}</div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-weight: 800; font-size: 0.98rem; color: var(--color-indigo);">
                                    ${{ number_format($item->price_usd ?? $item->price, 2) }}
                                </div>
                                <div style="font-size: 0.72rem; color: var(--color-text-muted);">
                                    USD &bull; Pre-order
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @auth
                <div style="padding-top: 1rem; border-top: 1px solid var(--color-border); display: flex; flex-direction: column; gap: 0.75rem;">
                    @if($event->is_ordering_open)
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('preorder-modal').style.display='block'" style="width: 100%; text-align: center; font-weight: 700;">
                            <span>🛒</span>
                            <span>Pre-Order Caribbean Meals Now</span>
                        </button>
                    @endif
                    <a href="{{ route('attendee.dashboard') }}" class="btn btn-secondary" style="width: 100%; text-align: center;">
                        <span>🎟️</span>
                        <span>View My Digital Tickets & Passes</span>
                    </a>
                </div>

                <!-- Pre-order Modal Dialog -->
                @if($event->is_ordering_open && !empty($event->menuItems) && count($event->menuItems) > 0)
                <div id="preorder-modal" style="display: none; position: fixed; inset: 0; background: rgba(17, 24, 39, 0.6); backdrop-filter: blur(4px); z-index: 50; overflow-y: auto; padding: 2rem 1rem;">
                    <div class="card" style="max-width: 580px; margin: 2rem auto; background: var(--color-white); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); padding: 2rem;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; border-bottom: 1px solid var(--color-border); padding-bottom: 1rem;">
                            <div>
                                <h3 style="font-size: 1.35rem; font-weight: 800; color: var(--color-navy);">Pre-Order Event Dining</h3>
                                <p style="font-size: 0.85rem; color: var(--color-text-secondary); margin-top: 0.2rem;">Select your meal portions for {{ $event->title }}.</p>
                            </div>
                            <button type="button" onclick="document.getElementById('preorder-modal').style.display='none'" style="background: none; border: none; font-size: 1.25rem; color: var(--color-text-muted); cursor: pointer;">✕</button>
                        </div>

                        <form action="{{ route('orders.store', $event->slug ?? $event->id) }}" method="POST" id="order-form">
                            @csrf
                            <input type="hidden" name="currency" value="USD">
                            <input type="hidden" name="customer_name" value="{{ Auth::user()->name }}">
                            <input type="hidden" name="email" value="{{ Auth::user()->email }}">

                            <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1.5rem;">
                                <div style="font-weight: 700; font-size: 0.88rem; color: var(--color-navy);">Select Dishes:</div>
                                @foreach($event->menuItems as $idx => $item)
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-bg-page);">
                                        <div>
                                            <div style="font-weight: 700; font-size: 0.9rem; color: var(--color-navy);">{{ $item->name }}</div>
                                            <div style="font-size: 0.8rem; color: var(--color-indigo); font-weight: 600;">${{ number_format($item->price_usd ?? $item->price, 2) }} USD</div>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <input type="hidden" name="items[{{ $idx }}][menu_item_id]" value="{{ $item->id }}">
                                            <label style="font-size: 0.8rem; color: var(--color-text-secondary);">Qty:</label>
                                            <input type="number" name="items[{{ $idx }}][quantity]" value="{{ $idx === 0 ? 1 : 0 }}" min="0" max="20" style="width: 60px; padding: 0.35rem; border: 1px solid var(--color-border); border-radius: var(--radius-sm); text-align: center; font-weight: 600;">
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1.5rem;">
                                <div>
                                    <label style="font-size: 0.82rem; font-weight: 600; color: var(--color-text-primary); display: block; margin-bottom: 0.25rem;">Contact Mobile Phone (for SMS Pass)</label>
                                    <input type="tel" name="customer_phone" value="{{ Auth::user()->phone ?? '+1 (305) 555-0199' }}" required style="width: 100%; padding: 0.65rem; border: 1px solid var(--color-border); border-radius: var(--radius-sm);">
                                </div>

                                <div>
                                    <label style="font-size: 0.82rem; font-weight: 600; color: var(--color-text-primary); display: block; margin-bottom: 0.25rem;">Payment Method</label>
                                    <select name="method" required style="width: 100%; padding: 0.65rem; border: 1px solid var(--color-border); border-radius: var(--radius-sm); background: var(--color-white);">
                                        <option value="cash">Cash on Arrival (Pay at Venue)</option>
                                        <option value="bank">US Domestic Bank Transfer (ACH / Wire)</option>
                                        <option value="zelle">Zelle Transfer</option>
                                    </select>
                                </div>
                            </div>

                            <div style="padding: 1rem; background: var(--color-indigo-soft); border-radius: var(--radius-md); margin-bottom: 1.5rem; font-size: 0.82rem; color: var(--color-navy); line-height: 1.5;">
                                <strong>Notice:</strong> Your order will receive a unique reference code and digital voucher. Manual transfers are verified against bank statements before final fulfillment.
                            </div>

                            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                                <button type="button" class="btn btn-secondary" onclick="document.getElementById('preorder-modal').style.display='none'">Cancel</button>
                                <button type="submit" class="btn btn-primary" style="font-weight: 700;">Confirm & Place Pre-Order</button>
                            </div>
                        </form>
                    </div>
                </div>
                @endif
            @endauth
        </div>
    </div>
</div>
@endsection
