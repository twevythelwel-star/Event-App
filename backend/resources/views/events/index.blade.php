@extends('layouts.app')

@section('title', 'Browse Events — Island Events & Ticketing')
@section('page-title', 'Public Events')

@section('content')
<div class="space-y-8">
    <!-- Public Welcome Hero -->
    <div class="card" style="background: linear-gradient(135deg, var(--color-indigo) 0%, #1e1b4b 100%); color: var(--color-white); border: none; padding: 2.25rem;">
        <div style="max-width: 720px; display: flex; flex-direction: column; gap: 0.85rem;">
            <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(255, 255, 255, 0.15); border-radius: 9999px; padding: 0.3rem 0.85rem; width: fit-content; font-size: 0.78rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">
                <span>🌴</span>
                <span>United States Event Marketplace</span>
            </div>
            <h1 style="font-size: 2rem; font-weight: 800; line-height: 1.2; letter-spacing: -0.025em; color: var(--color-white);">
                Discover Caribbean Food Festivals & Live Cultural Events
            </h1>
            <p style="font-size: 0.95rem; color: #E0E7FF; line-height: 1.6;">
                Reserve admission tickets, pre-order authentic Caribbean meals (escovitch fish, jerk chicken, festival), and receive digital QR passes for lightning-fast festival check-in.
            </p>

            @guest
            <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 0.75rem;">
                <a href="{{ route('register') }}" class="btn btn-primary" style="background: var(--color-white); color: var(--color-indigo); font-weight: 700; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                    <span>✨</span>
                    <span>Sign Up to Order</span>
                </a>
                <a href="{{ route('login') }}" class="btn btn-secondary" style="background: rgba(255, 255, 255, 0.12); color: var(--color-white); border-color: rgba(255, 255, 255, 0.25);">
                    <span>🔑</span>
                    <span>Already Registered? Log In</span>
                </a>
            </div>
            @endguest
        </div>
    </div>

    <!-- Events Grid Section -->
    <div>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <div>
                <h2 style="font-size: 1.35rem; font-weight: 700; color: var(--color-navy);">Upcoming Events</h2>
                <p style="font-size: 0.85rem; color: var(--color-text-secondary);">Browse active culinary festivals, food pre-ordering, and live events across the United States.</p>
            </div>
            <div style="font-size: 0.82rem; font-weight: 600; color: var(--color-text-secondary); background: var(--color-white); padding: 0.4rem 0.85rem; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                {{ count($events) }} Events Published
            </div>
        </div>

        @if(empty($events) || count($events) === 0)
            <div class="card empty-state">
                <div class="empty-state-icon">🌴</div>
                <div class="empty-state-title">No Events Available Yet</div>
                <div class="empty-state-desc">Event hosts are currently finalizing their culinary schedules and ticket tiers. Check back soon!</div>
                @guest
                    <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Create Attendee Account</a>
                @endguest
            </div>
        @else
            <div class="grid-3">
                @foreach($events as $event)
                    <div class="card" style="padding: 0; overflow: hidden; display: flex; flex-direction: column; transition: transform 0.2s, box-shadow 0.2s;">
                        <!-- Flyer / Banner -->
                        <div style="height: 180px; background: linear-gradient(135deg, #312E81 0%, #1E1B4B 100%); position: relative; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                            @if(!empty($event->flyer_url))
                                <img src="{{ $event->flyer_url }}" alt="{{ $event->title }}" style="width: 100%; height: 100%; object-fit: cover;">
                            @else
                                <div style="text-align: center; color: var(--color-white); opacity: 0.85;">
                                    <span style="font-size: 3rem;">🌴</span>
                                    <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 0.25rem;">Island Events Pass</div>
                                </div>
                            @endif

                            <div style="position: absolute; top: 12px; right: 12px;">
                                @if($event->is_ordering_open)
                                    <span class="badge badge-active" style="background: rgba(220, 252, 231, 0.95);">Kitchen Open</span>
                                @else
                                    <span class="badge" style="background: rgba(241, 245, 249, 0.95); color: var(--color-text-secondary);">Advance Sales Only</span>
                                @endif
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div style="padding: 1.25rem; display: flex; flex-direction: column; flex: 1;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.78rem; font-weight: 600; color: var(--color-indigo); margin-bottom: 0.35rem;">
                                <span>📅</span>
                                <span>{{ $event->date ? (is_string($event->date) ? date('M j, Y', strtotime($event->date)) : $event->date->format('M j, Y')) : 'Date TBA' }}</span>
                                @if(!empty($event->start_time))
                                    <span>&bull;</span>
                                    <span>{{ $event->start_time }}</span>
                                @endif
                            </div>

                            <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--color-navy); margin-bottom: 0.4rem; line-height: 1.3;">
                                {{ $event->title }}
                            </h3>

                            <p style="font-size: 0.82rem; color: var(--color-text-secondary); line-height: 1.5; margin-bottom: 1rem; flex: 1;">
                                {{ \Illuminate\Support\Str::limit($event->description ?? 'Join us for Caribbean dining, island music, and celebration.', 110) }}
                            </p>

                            <div style="display: flex; align-items: center; justify-content: space-between; padding-top: 0.85rem; border-top: 1px solid var(--color-border-subtle); margin-top: auto;">
                                <div style="font-size: 0.78rem; color: var(--color-text-muted); display: flex; align-items: center; gap: 0.3rem;">
                                    <span>📍</span>
                                    <span>{{ $event->venue ?? 'United States' }}</span>
                                </div>

                                <a href="{{ route('events.show', $event->id) }}" class="btn btn-primary btn-sm">
                                    View Details &rarr;
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
