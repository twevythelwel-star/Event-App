{{--
    The Explore screen's category tiles (config photo_library.collections): a two-column grid of photo tiles, each
    linking to /events?kind=<collection>. A tile's photo is an approved library photo of one of its kinds (labelled
    illustrative), else the cover of one of its upcoming events. Only collections with upcoming public events show,
    so a tile never leads to an empty list.
    Props: active (the collection key being shown, if any), headingLevel.
--}}
@props(['active' => null, 'headingLevel' => 2])

@php
    $library = app(\App\Services\PhotoLibrary::class);
    $subjects = \App\Services\PhotoLibrary::subjects();
    $tiles = [];
    foreach ((array) config('photo_library.collections') as $key => $collection) {
        $keys = array_values(array_intersect((array) ($collection['subjects'] ?? []), array_keys($subjects)));
        if ($keys === []) {
            continue;
        }
        $upcoming = \App\Models\Event::allTenants()->where('status', 'published')->where('visibility', 'public')
            ->whereIn('photo_subject', $keys)
            ->where(fn ($q) => $q->whereNull('event_date_iso')->orWhereDate('event_date_iso', '>=', now()->toDateString()));
        $count = (clone $upcoming)->count();
        if ($count === 0) {
            continue;
        }
        $photo = collect($keys)->map(fn ($subject) => $library->forSubject($subject)->first())->filter()->first();
        // A photograph: an approved library photo of these kinds, else an upcoming event's own uploaded flyer.
        // Generated flyers are text, so without a photograph the tile shows EventBite's original artwork instead.
        $image = $photo
            ? \App\Support\ResponsiveImage::forLibraryPhoto($photo)
            : $upcoming->orderByRaw('event_date_iso IS NULL')->orderBy('event_date_iso')->get()->first(fn ($e) => $e->hasFlyer())?->coverImage();
        $art = $image ? null : \App\Support\VisualAssets::heroFor($collection['art'] ?? $collection['label']);
        $tiles[] = ['key' => $key, 'label' => $collection['label'], 'count' => $count, 'image' => $image, 'art' => $art];
    }
    $tag = 'h' . max(2, min(4, (int) $headingLevel));
@endphp

@if($tiles !== [])
    <ul {{ $attributes->merge(['class' => 'eb-explore-tiles']) }}>
        @foreach($tiles as $i => $tile)
            <li>
                <a href="{{ route('events.index', ['kind' => $tile['key']]) }}" class="eb-explore-tile"
                   @if($active === $tile['key']) aria-current="page" @endif>
                    @if($tile['image'])
                        {{-- Decorative: the tile's name says what it is. --}}
                        <x-responsive-image :image="$tile['image']" alt="" :eager="$i < 2" sizes="(min-width: 1024px) 280px, (min-width: 640px) 30vw, 46vw" />
                    @elseif($tile['art'])
                        <img src="{{ $tile['art']['url'] }}" alt="" width="400" height="400" loading="{{ $i < 2 ? 'eager' : 'lazy' }}" decoding="async">
                    @endif
                    <span class="eb-explore-tile-scrim" aria-hidden="true"></span>
                    <{{ $tag }} class="eb-explore-tile-label">{{ $tile['label'] }}</{{ $tag }}>
                    <span class="eb-explore-tile-count">{{ $tile['count'] }} {{ \Illuminate\Support\Str::plural('event', $tile['count']) }}@if($tile['image']?->illustrative) · illustrative photo @endif</span>
                </a>
            </li>
        @endforeach
    </ul>
@endif
