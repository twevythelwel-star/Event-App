{{--
    Table shell: scroll wrapper, caption, header row and an empty state.
    Props: caption (read by screen readers; visible when showCaption), columns (list of header labels,
           or ['label' => ..., 'numeric' => true]), empty (bool: no rows), emptyTitle, emptyText.
    Rows (<tr>…</tr>) go in the slot.
--}}
@props([
    'caption',
    'columns' => [],
    'empty' => false,
    'emptyTitle' => 'Nothing here yet',
    'emptyText' => null,
    'showCaption' => false,
])

@if($empty)
    <x-empty-state :title="$emptyTitle" :description="$emptyText" />
@else
    <div {{ $attributes->merge(['class' => 'eb-table-wrap']) }}>
        <table class="eb-table">
            <caption class="{{ $showCaption ? 'eb-text-section-title' : 'sr-only' }}" style="text-align: left;">{{ $caption }}</caption>
            <thead>
                <tr>
                    @foreach($columns as $column)
                        @php $col = is_array($column) ? $column : ['label' => $column]; @endphp
                        <th scope="col" @class(['eb-table-num' => $col['numeric'] ?? false])>{{ $col['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                {{ $slot }}
            </tbody>
        </table>
    </div>
@endif
