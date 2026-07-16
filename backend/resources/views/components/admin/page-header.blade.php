@props(['title', 'description' => null])

<div class="admin-page-header">
    <div>
        <h2 class="brand-section-title">{{ $title }}</h2>
        @if ($description)
            <p class="mt-1 max-w-2xl text-sm text-sh-mid">{{ $description }}</p>
        @endif
    </div>
    @if (isset($actions))
        <div class="flex flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endif
</div>
