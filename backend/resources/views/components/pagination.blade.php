{{--
    Previous / page x of y / Next for a LengthAwarePaginator. Renders nothing when there are no rows.
    Props: paginator.
--}}
@props(['paginator'])

@if($paginator->total() > 0)
    <div {{ $attributes->merge(['class' => 'eb-pagination ac-pagination']) }}>
        <span>Showing {{ number_format($paginator->firstItem()) }}–{{ number_format($paginator->lastItem()) }} of {{ number_format($paginator->total()) }}</span>
        @if($paginator->hasPages())
            <nav class="eb-pagination-nav" aria-label="Pages">
                @if($paginator->onFirstPage())
                    <span class="eb-btn eb-btn-secondary eb-btn-sm ac-btn ac-btn-sm" aria-disabled="true">Previous</span>
                @else
                    <a class="eb-btn eb-btn-secondary eb-btn-sm ac-btn ac-btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
                @endif
                <span class="eb-pagination-current" aria-current="page">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
                @if($paginator->hasMorePages())
                    <a class="eb-btn eb-btn-secondary eb-btn-sm ac-btn ac-btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
                @else
                    <span class="eb-btn eb-btn-secondary eb-btn-sm ac-btn ac-btn-sm" aria-disabled="true">Next</span>
                @endif
            </nav>
        @endif
    </div>
@endif
