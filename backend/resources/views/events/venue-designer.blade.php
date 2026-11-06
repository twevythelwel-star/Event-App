@extends('layouts.app')

@section('title', 'Venue designer — ' . $event->title)
@section('page-title', 'Venue designer')

@section('content')
@php
    $rows = old('areas', $areas->map(fn ($a) => $a->only(['id', 'kind', 'label', 'x', 'y', 'width', 'height', 'ticket_tier_id', 'capacity', 'rows', 'seats_per_row', 'seats_per_table', 'is_accessible', 'notes']))->all());
    $kinds = \App\Models\VenueArea::KINDS;
    $tierOptions = ['' => 'No ticket type'] + $tiers->pluck('name', 'id')->all();
@endphp
<div class="eb-stack" style="gap: var(--space-5);">
    <div>
        <p class="eb-text-supporting" style="margin: 0;"><a href="{{ route('events.edit', $event->id) }}">← {{ $event->title }}</a></p>
        <h1 class="eb-text-page-title" style="margin: 0;">Venue designer</h1>
        <p class="eb-text-supporting" style="margin: 0;">Draw the venue: admission areas, VIP sections, reserved tables, rows of seats, food stations, vendor booths, stages and restricted areas. Drag an area to move it and its corner to resize it, or type its position below.</p>
    </div>
    @if($errors->any())
        <x-alert type="error" title="Not saved" id="venue-errors" tabindex="-1">
            <ul style="margin: 4px 0 0; padding-left: 1.1rem;">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
        </x-alert>
    @endif
    @if($tiers->isEmpty())
        <x-alert type="warning" title="No ticket types yet">Add ticket types to the event first; seated areas sell a ticket type.</x-alert>
    @endif

    <form method="POST" action="{{ route('events.venue-designer.save', $event->id) }}" class="eb-stack" style="gap: var(--space-4);" data-venue-form>
        @csrf
        <div class="eb-panel" style="margin: 0;">
            <div class="eb-cluster" style="justify-content: space-between; margin-bottom: var(--space-3);">
                <div class="eb-cluster" role="group" aria-label="Add an area">
                    @foreach($kinds as $key => $label)
                        <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" data-add-area="{{ $key }}">＋ {{ $label }}</button>
                    @endforeach
                </div>
            </div>
            <div class="eb-venue-canvas-scroll">
                <svg viewBox="0 0 {{ $layout['width'] }} {{ $layout['height'] }}" class="eb-venue-canvas" data-venue-canvas data-width="{{ $layout['width'] }}" data-height="{{ $layout['height'] }}"
                     role="img" aria-label="Venue plan. Each area is also listed below with its position and size.">
                    <defs><pattern id="venue-grid" width="20" height="20" patternUnits="userSpaceOnUse"><path d="M20 0H0V20" fill="none" stroke="currentColor" stroke-opacity="0.08"/></pattern></defs>
                    <rect width="100%" height="100%" fill="url(#venue-grid)"/>
                    <g data-venue-shapes></g>
                </svg>
            </div>
        </div>

        <ol class="eb-stack eb-venue-areas" style="gap: var(--space-3); list-style: none; margin: 0; padding: 0;" data-venue-rows>
            @foreach($rows as $i => $row)
                @include('events.partials.venue-area-row', ['i' => $i, 'row' => $row, 'counts' => $seatCounts[$row['id'] ?? ''] ?? null])
            @endforeach
        </ol>
        <template data-venue-template>@include('events.partials.venue-area-row', ['i' => '__I__', 'row' => [], 'counts' => null])</template>

        <div class="eb-panel eb-stack" style="gap: var(--space-3); margin: 0;">
            <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="seat_selection_enabled" value="1" @checked(old('seat_selection_enabled', $layout['seat_selection_enabled']))>
                <span><strong>Let buyers choose their seats.</strong> Buyers of ticket types linked to tables or seat rows pick a seat for each ticket at checkout.</span></label>
            <x-form-field name="notes" id="venue-notes" type="textarea" label="Notes for staff (optional)" :value="old('notes', $layout['notes'])" />
            <div><button type="submit" class="eb-btn eb-btn-primary" data-prevent-double-submit="true">Save venue plan</button></div>
        </div>
    </form>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('[data-venue-form]');
        const svg = form.querySelector('[data-venue-canvas]');
        const shapes = svg.querySelector('[data-venue-shapes]');
        const list = form.querySelector('[data-venue-rows]');
        const template = form.querySelector('[data-venue-template]');
        const W = Number(svg.dataset.width), H = Number(svg.dataset.height);
        const NS = 'http://www.w3.org/2000/svg';
        let next = list.children.length;
        const field = (row, name) => row.querySelector('[data-field="' + name + '"]');
        const num = (row, name) => Number(field(row, name).value || 0);
        const clamp = (v, lo, hi) => Math.max(lo, Math.min(hi, v));

        function showFieldsFor(row) {
            const kind = field(row, 'kind').value;
            row.querySelectorAll('[data-for]').forEach(el => { el.hidden = !el.dataset.for.split(' ').includes(kind); });
        }
        function draw() {
            shapes.innerHTML = '';
            Array.from(list.children).forEach(function (row, index) {
                const g = document.createElementNS(NS, 'g');
                g.setAttribute('class', 'eb-plan-shape');
                g.dataset.kind = field(row, 'kind').value;
                const x = num(row, 'x'), y = num(row, 'y'), w = num(row, 'width'), h = num(row, 'height');
                const body = document.createElementNS(NS, g.dataset.kind === 'table' ? 'ellipse' : 'rect');
                if (g.dataset.kind === 'table') {
                    body.setAttribute('cx', x + w / 2); body.setAttribute('cy', y + h / 2); body.setAttribute('rx', w / 2); body.setAttribute('ry', h / 2);
                } else {
                    body.setAttribute('x', x); body.setAttribute('y', y); body.setAttribute('width', w); body.setAttribute('height', h); body.setAttribute('rx', 8);
                }
                body.setAttribute('class', 'eb-plan-area');
                const text = document.createElementNS(NS, 'text');
                text.setAttribute('x', x + 8); text.setAttribute('y', y + 20); text.setAttribute('class', 'eb-plan-label');
                text.textContent = (index + 1) + '. ' + (field(row, 'label').value || '');
                const handle = document.createElementNS(NS, 'rect');
                handle.setAttribute('x', x + w - 14); handle.setAttribute('y', y + h - 14); handle.setAttribute('width', 14); handle.setAttribute('height', 14);
                handle.setAttribute('class', 'eb-plan-handle');
                g.append(body, text, handle);
                shapes.append(g);
                g.addEventListener('pointerdown', start(row, false));
                handle.addEventListener('pointerdown', start(row, true));
            });
        }
        function point(event) {
            const box = svg.getBoundingClientRect();
            return { x: (event.clientX - box.left) / box.width * W, y: (event.clientY - box.top) / box.height * H };
        }
        function start(row, resizing) {
            return function (event) {
                event.preventDefault(); event.stopPropagation();
                const from = point(event);
                const orig = { x: num(row, 'x'), y: num(row, 'y'), w: num(row, 'width'), h: num(row, 'height') };
                function move(e) {
                    const p = point(e), dx = Math.round((p.x - from.x) / 10) * 10, dy = Math.round((p.y - from.y) / 10) * 10;
                    if (resizing) {
                        field(row, 'width').value = clamp(orig.w + dx, 20, W - orig.x);
                        field(row, 'height').value = clamp(orig.h + dy, 20, H - orig.y);
                    } else {
                        field(row, 'x').value = clamp(orig.x + dx, 0, W - orig.w);
                        field(row, 'y').value = clamp(orig.y + dy, 0, H - orig.h);
                    }
                    draw();
                }
                function stop() { window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', stop); }
                window.addEventListener('pointermove', move);
                window.addEventListener('pointerup', stop);
            };
        }
        form.querySelectorAll('[data-add-area]').forEach(function (button) {
            button.addEventListener('click', function () {
                list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__I__', String(next++)));
                const row = list.lastElementChild;
                const kind = button.dataset.addArea;
                field(row, 'kind').value = kind;
                field(row, 'label').value = button.textContent.replace('＋', '').trim() + ' ' + list.children.length;
                const size = kind === 'table' ? [100, 100] : (kind === 'seat_rows' ? [300, 160] : [200, 140]);
                field(row, 'width').value = size[0]; field(row, 'height').value = size[1];
                // New areas are staggered so they do not land on top of each other.
                const step = (list.children.length - 1) % 8;
                field(row, 'x').value = Math.min(W - size[0], 20 + step * 40); field(row, 'y').value = Math.min(H - size[1], 20 + step * 30);
                showFieldsFor(row); draw();
                field(row, 'label').focus();
            });
        });
        list.addEventListener('input', draw);
        list.addEventListener('change', function (event) {
            const row = event.target.closest('[data-venue-row]');
            if (row) showFieldsFor(row);
            draw();
        });
        list.addEventListener('click', function (event) {
            const remove = event.target.closest('[data-remove-area]');
            if (!remove) return;
            if (remove.dataset.sold > 0) { alert('This area has sold seats and cannot be removed.'); return; }
            remove.closest('[data-venue-row]').remove();
            draw();
        });
        Array.from(list.children).forEach(showFieldsFor);
        draw();
    });
</script>
@endsection
