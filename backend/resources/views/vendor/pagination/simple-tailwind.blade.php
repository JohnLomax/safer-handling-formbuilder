@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex gap-2 items-center justify-between">

        @if ($paginator->onFirstPage())
            <span class="inline-flex items-center rounded-[10px] border border-[#d8e8f8] bg-white px-4 py-2 text-sm font-medium text-sh-mid/60 cursor-not-allowed leading-5">
                {!! __('pagination.previous') !!}
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center rounded-[10px] border border-[#b7d3ee] bg-white px-4 py-2 text-sm font-semibold text-[#0f4a78] leading-5 transition hover:border-brand hover:bg-[#f7fbff] hover:text-brand focus:outline-none focus:ring-2 focus:ring-brand/30">
                {!! __('pagination.previous') !!}
            </a>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center rounded-[10px] border border-[#b7d3ee] bg-white px-4 py-2 text-sm font-semibold text-[#0f4a78] leading-5 transition hover:border-brand hover:bg-[#f7fbff] hover:text-brand focus:outline-none focus:ring-2 focus:ring-brand/30">
                {!! __('pagination.next') !!}
            </a>
        @else
            <span class="inline-flex items-center rounded-[10px] border border-[#d8e8f8] bg-white px-4 py-2 text-sm font-medium text-sh-mid/60 cursor-not-allowed leading-5">
                {!! __('pagination.next') !!}
            </span>
        @endif

    </nav>
@endif
