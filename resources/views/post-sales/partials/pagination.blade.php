@if($pager && $pager->hasPages())
<div class="ps-pagination"><span>Page {{ $pager->currentPage() }} / {{ $pager->lastPage() }}</span>@if($pager->onFirstPage())<span><i class="fas fa-chevron-left"></i></span>@else<a href="{{ $pager->previousPageUrl() }}"><i class="fas fa-chevron-left"></i></a>@endif @if($pager->hasMorePages())<a href="{{ $pager->nextPageUrl() }}"><i class="fas fa-chevron-right"></i></a>@else<span><i class="fas fa-chevron-right"></i></span>@endif</div>
@endif
