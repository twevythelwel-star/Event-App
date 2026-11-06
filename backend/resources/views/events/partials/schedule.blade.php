{{--
    The event schedule as attendees see it: grouped by day, each activity with its time, place, kind and the people
    on, plus profiles of the line-up. Props: $schedule (ScheduleService::publicSchedule), $performers (collection),
    $headingLevel (2 or 3).
--}}
@php
    $h = 'h' . (int) ($headingLevel ?? 2);
    $sub = 'h' . ((int) ($headingLevel ?? 2) + 1);
    $stageNames = $schedule->flatten(1)->pluck('stage')->filter()->unique()->values();
    $lineup = collect($performers ?? [])->filter(fn ($p) => $p->activities->contains('is_public', true) || $p->activities->isEmpty());
@endphp
@if($schedule->isNotEmpty())
<section class="eb-schedule" aria-labelledby="schedule-heading">
    <div class="eb-schedule-head">
        <{{ $h }} id="schedule-heading" class="eb-text-section-title" style="margin: 0;">Schedule</{{ $h }}>
        @if($stageNames->count() > 1)
            <label class="eb-schedule-filter">
                <span class="eb-text-label">Show</span>
                <select class="eb-select eb-input" data-schedule-filter aria-label="Show activities at">
                    <option value="">All places</option>
                    @foreach($stageNames as $stage)<option value="{{ $stage }}">{{ $stage }}</option>@endforeach
                </select>
            </label>
        @endif
    </div>
    @foreach($schedule as $date => $rows)
        <{{ $sub }} class="eb-schedule-day">{{ \Illuminate\Support\Carbon::parse($date)->format('l, F j') }}</{{ $sub }}>
        <ol class="eb-schedule-list">
            @foreach($rows as $row)
                <li class="eb-schedule-item" data-kind="{{ $row['kind'] }}" data-stage="{{ $row['stage'] }}">
                    <p class="eb-schedule-time">
                        <time datetime="{{ $row['starts']->toIso8601String() }}">{{ $row['starts']->format('g:i A') }}</time>
                        <span aria-hidden="true">–</span><span class="sr-only">to</span>
                        <time datetime="{{ $row['ends']->toIso8601String() }}">{{ $row['ends']->format('g:i A') }}</time>
                    </p>
                    <div class="eb-schedule-body">
                        <p class="eb-schedule-title">{{ $row['title'] }}</p>
                        <p class="eb-schedule-meta">
                            <span class="eb-badge eb-badge-info">{{ $row['kind_label'] }}</span>
                            @if($row['stage'])<span><x-icon name="map-pin" size="sm" /> {{ $row['stage'] }}</span>@endif
                        </p>
                        @if($row['performers']->isNotEmpty())
                            <p class="eb-schedule-people">With {{ $row['performers']->pluck('name')->join(', ', ' and ') }}</p>
                        @endif
                        @if($row['description'])<p class="eb-help-text" style="margin: 0;">{{ $row['description'] }}</p>@endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endforeach
    <p class="eb-help-text" style="margin: 0;">Times are in the event's local time ({{ $schedule->first()->first()['starts']->format('T') }}). The host may change the schedule.</p>

    @if($lineup->isNotEmpty())
        <{{ $sub }} class="eb-schedule-day" id="lineup-heading">Line-up</{{ $sub }}>
        <ul class="eb-lineup" aria-labelledby="lineup-heading">
            @foreach($lineup as $person)
                <li class="eb-lineup-card">
                    @if($photo = $person->photo())
                        <x-responsive-image :image="$photo" :alt="$person->photo_alt_text ?: 'Photo of ' . $person->name" sizes="96px" class="eb-lineup-photo" width="96" height="96" />
                    @else
                        {{-- No photo from the host: their initials, never a stock picture of someone else. --}}
                        <span class="eb-lineup-photo eb-lineup-initials" aria-hidden="true">{{ collect(preg_split('/\s+/', trim($person->name)))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->join('') }}</span>
                    @endif
                    <div>
                        <p class="eb-schedule-title" style="margin: 0;">{{ $person->name }}</p>
                        <p class="eb-text-label" style="margin: 0;">{{ $person->roleLabel() }}@if($person->headline) · {{ $person->headline }}@endif</p>
                        @if($person->bio)<p class="eb-help-text" style="margin: 4px 0 0;">{{ \Illuminate\Support\Str::limit($person->bio, 220) }}</p>@endif
                        @if($person->safeLinks())
                            <p class="eb-lineup-links">@foreach($person->safeLinks() as $link)<a href="{{ $link['url'] }}" rel="noopener nofollow" target="_blank">{{ $link['label'] }}<span class="sr-only"> (opens in a new tab)</span></a>@endforeach</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
<script>
    document.querySelectorAll('[data-schedule-filter]').forEach(function (select) {
        select.addEventListener('change', function () {
            const section = select.closest('.eb-schedule');
            section.querySelectorAll('.eb-schedule-item').forEach(function (item) {
                item.hidden = select.value !== '' && item.dataset.stage !== select.value;
            });
        });
    });
</script>
@endif
