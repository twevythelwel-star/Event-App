@if($paginator->total() > 0)
    <div class="ac-pagination">
        <span>Showing {{ number_format($paginator->firstItem()) }}–{{ number_format($paginator->lastItem()) }} of {{ number_format($paginator->total()) }}</span>
        @if($paginator->hasPages())
            <nav aria-label="Pages">
                @if($paginator->onFirstPage())
                    <span class="ac-btn ac-btn-sm" aria-disabled="true">Previous</span>
                @else
                    <a class="ac-btn ac-btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
                @endif
                <span class="ac-btn ac-btn-sm" aria-current="page" style="cursor:default">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
                @if($paginator->hasMorePages())
                    <a class="ac-btn ac-btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
                @else
                    <span class="ac-btn ac-btn-sm" aria-disabled="true">Next</span>
                @endif
            </nav>
        @endif
    </div>
@endif
