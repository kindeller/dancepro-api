@if($paginator->hasPages())
    <nav class="admin-pagination" aria-label="Pagination">
        <div class="muted">Showing {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }} of {{ $paginator->total() }} results</div>
        <div class="admin-pagination-links">
            @if($paginator->onFirstPage())
                <span class="admin-page-link disabled" aria-disabled="true">Previous</span>
            @else
                <a class="admin-page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
            @endif

            @foreach($paginator->getUrlRange(1, $paginator->lastPage()) as $page => $url)
                @if($page === $paginator->currentPage())
                    <span class="admin-page-link current" aria-current="page">{{ $page }}</span>
                @else
                    <a class="admin-page-link" href="{{ $url }}">{{ $page }}</a>
                @endif
            @endforeach

            @if($paginator->hasMorePages())
                <a class="admin-page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span class="admin-page-link disabled" aria-disabled="true">Next</span>
            @endif
        </div>
    </nav>
@endif
