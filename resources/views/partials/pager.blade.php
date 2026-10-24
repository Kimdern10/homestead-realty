@if ($paginator->hasPages())
    <nav class="pager" aria-label="Pages of results">
        @if ($paginator->onFirstPage())
            <span aria-disabled="true" aria-label="Previous page"><x-icon name="chevron-left" /></span>
        @else
            <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="window.scrollTo({ top: 0, behavior: 'smooth' })" aria-label="Previous page"><x-icon name="chevron-left" /></button>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span aria-disabled="true">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page" wire:key="page-{{ $page }}">{{ $page }}</span>
                    @else
                        <button type="button" wire:key="page-{{ $page }}" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" x-on:click="window.scrollTo({ top: 0, behavior: 'smooth' })" aria-label="Page {{ $page }}">{{ $page }}</button>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="window.scrollTo({ top: 0, behavior: 'smooth' })" aria-label="Next page"><x-icon name="chevron-right" /></button>
        @else
            <span aria-disabled="true" aria-label="Next page"><x-icon name="chevron-right" /></span>
        @endif
    </nav>
@endif
