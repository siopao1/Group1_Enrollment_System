{{-- Pagination styled like the main app: "Showing 1–10 of 42 records  ‹ 1 ›" --}}
@if ($paginator->total() > 0)
  <div class="pagination">
    <span>Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ number_format($paginator->total()) }} records</span>
    <div class="pager">
      @if ($paginator->onFirstPage())
        <span class="disabled" aria-hidden="true">‹</span>
      @else
        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page">‹</a>
      @endif
      <span class="pager-current">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
      @if ($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page">›</a>
      @else
        <span class="disabled" aria-hidden="true">›</span>
      @endif
    </div>
  </div>
@endif
