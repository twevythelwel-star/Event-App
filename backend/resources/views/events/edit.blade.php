@extends('layouts.app')

@section('title', 'Edit ' . $event->title . ' — EventBite')
@section('page-title', 'Edit event')

@php
    $menu = $event->menuItems()->withoutGlobalScopes()->orderBy('category')->orderBy('name')->get();
@endphp

@section('content')
<div class="eb-stack" style="gap: var(--layout-section-gap);">
    <x-page-header :title="$event->title"
                   :badge="ucfirst($event->status)"
                   description="Change details, tickets and the flyer. Publishing makes the event visible and lets people buy tickets."
                   :breadcrumbs="['Host dashboard' => route('host.dashboard'), $event->title => null]">
        <x-slot:actions>
            <x-button variant="secondary" size="sm" icon="eye" :href="route('events.preview', $event->id)">👁️ Preview</x-button>
            <x-button variant="ghost" size="sm" :href="route('events.show', $event->id)">View public page</x-button>
            <x-button variant="secondary" size="sm" icon="staff" :href="route('host.events.staff', $event->id)">Staff</x-button>
            @if($event->status === 'published')
                <form action="{{ route('events.toggle-ordering', $event->id) }}" method="POST" style="display: inline;">
                    @csrf
                    <button type="submit" class="eb-btn eb-btn-secondary eb-btn-sm">{{ $event->is_ordering_open ? 'Close ordering' : 'Open ordering' }}</button>
                </form>
                <form action="{{ route('events.unpublish', $event->id) }}" method="POST" style="display: inline;"
                      onsubmit="return confirm('Unpublish this event? It disappears from discovery and nobody can buy tickets until you publish it again.');">
                    @csrf
                    <button type="submit" class="eb-btn eb-btn-destructive eb-btn-sm">Unpublish</button>
                </form>
            @else
                {{-- Publishing goes through the review screen and its readiness check. --}}
                <x-button variant="primary" size="sm" :href="route('events.review', $event->id)">Review and publish</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if($event->status !== 'published')
        @php
            $readiness = app(\App\Services\EventManagementService::class)->publicationReadiness($event, auth()->user());
            $missing = array_values(array_filter($readiness, fn ($i) => $i['critical']));
        @endphp
        <x-alert :type="$missing ? 'warning' : 'success'" :title="$missing ? 'Draft: ' . count($missing) . ' ' . \Illuminate\Support\Str::plural('thing', count($missing)) . ' to finish before publishing' : 'Ready to publish'">
            @if($missing)
                <ul style="margin: 4px 0 0; padding-left: 1.1rem;">
                    @foreach(array_slice($missing, 0, 5) as $issue)
                        <li>{{ $issue['message'] }} <a href="{{ $issue['section'] === 'menu' ? route('events.menu-studio', $event->id) : '#studio-' . $issue['section'] }}">Go there</a></li>
                    @endforeach
                </ul>
                @if(count($missing) > 5)<p style="margin: 4px 0 0;">And {{ count($missing) - 5 }} more on the <a href="{{ route('events.review', $event->id) }}">review screen</a>.</p>@endif
            @else
                Everything needed is in place. <a href="{{ route('events.review', $event->id) }}">Review and publish</a>.
            @endif
            <span style="display: block; margin-top: 4px;">This draft is visible only to you; nothing can be bought until it is published.</span>
        </x-alert>
    @endif

    @if($errors->any() && !$errors->has('error'))
        <x-alert type="error" title="Please check the highlighted fields">{{ $errors->count() === 1 ? $errors->first() : $errors->count() . ' fields need attention.' }}</x-alert>
    @endif

    <form action="{{ route('events.update', $event->id) }}" method="POST" enctype="multipart/form-data" class="eb-stack" style="gap: var(--layout-section-gap);">
        @csrf
        @method('PUT')
        @include('events.partials.form', ['event' => $event])

        <div class="eb-cluster" style="justify-content: space-between;">
            <div>
                @if($event->flyer_path)
                    <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm"
                            onclick="if (confirm('Remove the flyer? The event shows its default artwork until you upload another.')) document.getElementById('delete-flyer-form').submit();">Remove flyer</button>
                @endif
            </div>
            <div class="eb-cluster">
                <x-button variant="ghost" :href="route('host.dashboard')">Cancel</x-button>
                <button type="submit" class="eb-btn eb-btn-primary" data-prevent-double-submit="true">Save changes</button>
            </div>
        </div>
    </form>

    <section class="eb-panel card" style="margin: 0;" id="menu">
        <div class="eb-card-header card-header"><div>
            <h2 class="eb-card-title card-title">Menu ({{ $menu->count() }} items)</h2>
            <p class="eb-card-subtitle card-subtitle">Items customers can order with their tickets. Adding and editing menu items is not available on this page yet.</p>
        </div></div>
        <x-data-table caption="Menu items" :columns="['Item', 'Category', ['label' => 'Price', 'numeric' => true], 'Available']"
                      :empty="$menu->isEmpty()" empty-title="No menu items" empty-text="This event does not sell food yet.">
            @foreach($menu as $item)
                <tr>
                    <td><div style="font-weight: 600;">{{ $item->name }}</div>@if($item->description)<div class="eb-help-text">{{ \Illuminate\Support\Str::limit($item->description, 90) }}</div>@endif</td>
                    <td>{{ ucfirst((string) $item->category) }}</td>
                    <td class="eb-table-num">{{ $item->price_usd !== null ? '$' . number_format((float) $item->price_usd, 2) : '—' }}</td>
                    <td><span class="eb-badge {{ $item->is_active ? 'eb-badge-success' : '' }}">{{ $item->is_active ? 'On sale' : 'Hidden' }}</span></td>
                </tr>
            @endforeach
        </x-data-table>
    </section>

    @if($qr ?? null)
        @include('events.partials.event-qr')
    @endif

    @if($event->flyer_path)
        <form id="delete-flyer-form" action="{{ route('events.flyer.remove', $event->id) }}" method="POST" hidden>
            @csrf
            @method('DELETE')
        </form>
    @endif
</div>
@endsection
