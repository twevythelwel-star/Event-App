{{--
    Seat selection on the tickets step. The plan is drawn to scale; every seat is a real checkbox named for screen
    readers ("Main hall, row B, seat 12, VIP Table ticket, available"), so it works by keyboard and touch as well as
    by pointer. The list under the plan offers the same seats grouped by area. The server checks the choice again
    when the step is saved, and once more under a lock when the order is placed.
--}}
@php
    $tierNames = collect($tiers)->pluck('name', 'id');
    $picked = (array) old('seats', $cart['seats'] ?? []);
    $w = $venue['width'];
    $h = $venue['height'];
    $byArea = $seatMap->groupBy(fn ($row) => $row['area']->id);
@endphp
<section aria-labelledby="seats-heading" class="eb-stack eb-seat-picker" style="gap: var(--space-3);" data-seat-picker>
    <h2 id="seats-heading" class="eb-text-section-title" style="margin: 0;">Choose your seats</h2>
    <p class="eb-text-supporting" style="margin: 0;">Pick one seat for each ticket of
        {{ collect($seatedTiers)->map(fn ($id) => $tierNames[$id] ?? null)->filter()->join(', ', ' and ') }}.
        Seats are held for you when you place the order.</p>
    <p class="eb-seat-counts" role="status" aria-live="polite" data-seat-counts></p>
    @error('seats')<x-alert type="error">{{ $message }}</x-alert>@enderror

    <div class="eb-seat-plan-scroll">
        <div class="eb-seat-plan" style="aspect-ratio: {{ $w }} / {{ $h }};">
            <svg viewBox="0 0 {{ $w }} {{ $h }}" class="eb-seat-plan-svg" aria-hidden="true" focusable="false">
                @foreach($venueAreas as $area)
                    @if($area->kind === 'table')
                        <ellipse cx="{{ $area->x + $area->width / 2 }}" cy="{{ $area->y + $area->height / 2 }}" rx="{{ $area->width * 0.28 }}" ry="{{ $area->height * 0.28 }}" class="eb-plan-area" data-kind="table"/>
                    @else
                        <rect x="{{ $area->x }}" y="{{ $area->y }}" width="{{ $area->width }}" height="{{ $area->height }}" rx="10" class="eb-plan-area" data-kind="{{ $area->kind }}"/>
                    @endif
                    <text x="{{ $area->x + 8 }}" y="{{ $area->y + 18 }}" class="eb-plan-label">{{ $area->label }}</text>
                @endforeach
            </svg>
            @foreach($seatMap as $row)
                @php $seat = $row['seat']; $tierId = (string) $row['area']->ticket_tier_id; @endphp
                <label class="eb-seat {{ $row['available'] ? '' : 'is-taken' }} {{ $seat->is_accessible ? 'is-accessible' : '' }}"
                       style="left: {{ round($seat->x / $w * 100, 3) }}%; top: {{ round($seat->y / $h * 100, 3) }}%;" title="{{ $seat->spokenName() }}">
                    <input type="checkbox" name="seats[]" value="{{ $seat->id }}" data-tier="{{ $tierId }}" class="sr-only"
                           @checked(in_array($seat->id, $picked, true)) @disabled(!$row['available'] || !in_array($tierId, $seatedTiers, true))>
                    <span class="sr-only">{{ $seat->spokenName() }}, {{ $tierNames[$tierId] ?? 'ticket' }}{{ $seat->is_accessible ? ', accessible' : '' }}, {{ $row['available'] ? 'available' : 'taken' }}</span>
                </label>
            @endforeach
        </div>
    </div>
    <ul class="eb-seat-legend" aria-label="Key">
        <li><span class="eb-seat-swatch"></span> Available</li>
        <li><span class="eb-seat-swatch is-picked"></span> Your seat</li>
        <li><span class="eb-seat-swatch is-taken"></span> Taken</li>
        <li><span class="eb-seat-swatch is-accessible"></span> Accessible</li>
    </ul>
    {{-- The same seats as lists, for anyone who prefers them to the plan. Both control the same checkboxes. --}}
    <details class="eb-panel" style="margin: 0;">
        <summary class="eb-text-label">Choose from a list instead</summary>
        @foreach($byArea as $rows)
            @php $area = $rows->first()['area']; @endphp
            <fieldset class="eb-fieldset" style="margin-top: var(--space-3);">
                <legend class="eb-text-label">{{ $area->label }} · {{ $tierNames[(string) $area->ticket_tier_id] ?? $area->kindLabel() }}</legend>
                <div class="eb-cluster">
                    @foreach($rows as $row)
                        <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" data-mirror="{{ $row['seat']->id }}" @disabled(!$row['available'])>
                            <span>{{ $row['seat']->row_label ? $row['seat']->row_label . $row['seat']->number : 'Seat ' . $row['seat']->number }}@unless($row['available']) (taken)@endunless</span></label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach
    </details>
</section>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const picker = document.querySelector('[data-seat-picker]');
        if (!picker) return;
        const form = picker.closest('form');
        const counts = picker.querySelector('[data-seat-counts]');
        const names = @json($tierNames);
        const seated = @json($seatedTiers);
        const seats = Array.from(picker.querySelectorAll('input[name="seats[]"]'));
        const mirrors = Array.from(picker.querySelectorAll('[data-mirror]'));
        function wanted(tier) {
            const input = form.querySelector('[name="tiers[' + tier + ']"]');
            return input ? Number(input.value || 0) : 0;
        }
        function refresh() {
            seats.forEach(s => s.closest('.eb-seat').classList.toggle('is-picked', s.checked));
            mirrors.forEach(m => { m.checked = picker.querySelector('input[name="seats[]"][value="' + m.dataset.mirror + '"]').checked; });
            counts.textContent = seated.map(tier => {
                const chosen = seats.filter(s => s.checked && s.dataset.tier === tier).length;
                return (names[tier] || 'Tickets') + ': ' + chosen + ' of ' + wanted(tier) + ' seats chosen';
            }).join(' · ');
        }
        seats.forEach(s => s.addEventListener('change', function () {
            // Never more seats than tickets of that type.
            if (s.checked && seats.filter(x => x.checked && x.dataset.tier === s.dataset.tier).length > wanted(s.dataset.tier)) {
                s.checked = false;
                counts.textContent = 'Add another ' + (names[s.dataset.tier] || '') + ' ticket first, or unselect a seat.';
                return;
            }
            refresh();
        }));
        mirrors.forEach(m => m.addEventListener('change', function () {
            const seat = picker.querySelector('input[name="seats[]"][value="' + m.dataset.mirror + '"]');
            seat.checked = m.checked;
            seat.dispatchEvent(new Event('change'));
        }));
        form.addEventListener('input', refresh);
        form.addEventListener('click', () => setTimeout(refresh, 0));
        refresh();
    });
</script>
