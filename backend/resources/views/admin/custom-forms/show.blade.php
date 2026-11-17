@php
    $qrSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&margin=12&data='.rawurlencode($publicUrl);
    $exportParams = array_filter([
        'q' => $search !== '' ? $search : null,
        'follow_up' => $followUpFilter !== 'all' ? $followUpFilter : null,
    ]);
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-admin.page-header
            :title="$form->name"
            :description="$form->title"
        >
            <x-slot name="actions">
                <a href="{{ route('admin.custom-forms.index') }}" class="btn-brand-outline text-xs">All forms</a>
                <a href="{{ route('admin.custom-forms.edit', $form) }}" class="btn-brand-outline text-xs">Edit</a>
                <a href="{{ route('admin.custom-forms.export', array_merge(['custom_form' => $form], $exportParams)) }}" class="btn-brand text-xs">Export CSV</a>
            </x-slot>
        </x-admin.page-header>
    </x-slot>

    <div class="admin-shell space-y-6">
        @include('admin.partials.alerts')

        <div class="grid gap-6 lg:grid-cols-[280px_1fr]">
            <div class="brand-panel text-center">
                <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">QR code</p>
                <img
                    src="{{ $qrSrc }}"
                    alt="QR code for {{ $form->name }}"
                    width="280"
                    height="280"
                    class="mx-auto mt-3 rounded-lg border border-sh-border bg-white p-2"
                />
                <a href="{{ $qrSrc }}" download="{{ $form->slug }}-qr.png" class="btn-brand-outline mt-4 inline-flex text-xs" target="_blank" rel="noopener">
                    Download QR
                </a>
                <p class="mt-3 break-all text-xs text-sh-mid" id="custom-form-public-url">{{ $publicUrl }}</p>
                <button
                    type="button"
                    class="btn-brand mt-3 text-xs"
                    x-data
                    @click="navigator.clipboard.writeText(@js($publicUrl)); $el.textContent = 'Copied'; setTimeout(() => $el.textContent = 'Copy link', 1500)"
                >
                    Copy link
                </button>
            </div>

            <div class="brand-panel space-y-4">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Status</p>
                        <p class="mt-1">
                            @if ($form->is_active)
                                <span class="status-pill status-pill-success">Active</span>
                            @else
                                <span class="status-pill status-pill-muted">Inactive</span>
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Total leads</p>
                        <p class="mt-1 text-sm font-semibold text-sh-text">{{ $totalSubmissions }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Want contact</p>
                        <p class="mt-1 text-sm font-semibold text-sh-text">{{ $followUpYesCount }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Info only</p>
                        <p class="mt-1 text-sm font-semibold text-sh-text">{{ $followUpInfoCount }}</p>
                    </div>
                </div>
                @if ($form->intro)
                    <p class="text-sm text-sh-mid">{{ $form->intro }}</p>
                @endif
                <p class="text-xs text-sh-mid">Print the QR for stands, flyers, or badges. Scanning opens the public form — no login required.</p>
            </div>
        </div>

        <div class="brand-panel min-w-0 !p-0">
            <div class="flex flex-col gap-4 border-b border-sh-border/80 px-6 py-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-brand-header">Submissions</h3>
                    <p class="mt-1 text-sm text-sh-mid">
                        {{ $submissions->total() }}
                        {{ $submissions->total() === 1 ? 'lead' : 'leads' }}
                        @if ($search !== '' || $followUpFilter !== 'all')
                            matching filters
                        @else
                            captured from this form
                        @endif
                    </p>
                </div>
                <a
                    href="{{ route('admin.custom-forms.export', array_merge(['custom_form' => $form], $exportParams)) }}"
                    class="btn-brand inline-flex shrink-0 items-center gap-2 text-xs"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12M12 16.5V3" />
                    </svg>
                    Export CSV
                </a>
            </div>

            <div class="flex flex-col gap-3 border-b border-sh-border/80 px-6 py-4 lg:flex-row lg:items-center lg:justify-between">
                <form method="GET" action="{{ route('admin.custom-forms.show', $form) }}" class="flex w-full flex-col gap-2 sm:flex-row sm:items-center lg:max-w-xl">
                    @if ($followUpFilter !== 'all')
                        <input type="hidden" name="follow_up" value="{{ $followUpFilter }}">
                    @endif
                    <x-text-input
                        name="q"
                        type="search"
                        class="block w-full"
                        :value="$search"
                        placeholder="Search name, email, organisation, phone…"
                    />
                    <button type="submit" class="btn-brand-outline shrink-0 text-xs">Search</button>
                    @if ($search !== '')
                        <a href="{{ route('admin.custom-forms.show', array_filter(['custom_form' => $form, 'follow_up' => $followUpFilter !== 'all' ? $followUpFilter : null])) }}" class="btn-brand-outline shrink-0 text-xs">Clear</a>
                    @endif
                </form>

                <div class="inline-flex flex-wrap gap-2">
                    <a href="{{ route('admin.custom-forms.show', array_filter(['custom_form' => $form, 'q' => $search !== '' ? $search : null])) }}" @class([
                        'rounded-[8px] border px-3 py-1.5 text-xs font-semibold transition',
                        'border-brand bg-[#eef6ff] text-brand-header' => $followUpFilter === 'all',
                        'border-sh-border bg-white text-sh-mid hover:border-brand hover:text-brand' => $followUpFilter !== 'all',
                    ])>All</a>
                    <a href="{{ route('admin.custom-forms.show', array_filter(['custom_form' => $form, 'follow_up' => 'yes', 'q' => $search !== '' ? $search : null])) }}" @class([
                        'rounded-[8px] border px-3 py-1.5 text-xs font-semibold transition',
                        'border-brand bg-[#eef6ff] text-brand-header' => $followUpFilter === 'yes',
                        'border-sh-border bg-white text-sh-mid hover:border-brand hover:text-brand' => $followUpFilter !== 'yes',
                    ])>Want contact ({{ $followUpYesCount }})</a>
                    <a href="{{ route('admin.custom-forms.show', array_filter(['custom_form' => $form, 'follow_up' => 'information_only', 'q' => $search !== '' ? $search : null])) }}" @class([
                        'rounded-[8px] border px-3 py-1.5 text-xs font-semibold transition',
                        'border-brand bg-[#eef6ff] text-brand-header' => $followUpFilter === 'information_only',
                        'border-sh-border bg-white text-sh-mid hover:border-brand hover:text-brand' => $followUpFilter !== 'information_only',
                    ])>Info only ({{ $followUpInfoCount }})</a>
                </div>
            </div>

            <div class="admin-table-wrap !rounded-none !border-0">
                <table class="admin-table admin-table-fixed">
                    <thead>
                        <tr>
                            <th class="w-[24%]">Contact</th>
                            <th class="w-[18%]">Organisation</th>
                            <th class="w-[32%]">Interests</th>
                            <th class="w-[14%]">Follow-up</th>
                            <th class="w-[12%]">Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($submissions as $submission)
                            @php $labels = $submission->interestLabels(); @endphp
                            <tr>
                                <td>
                                    <div class="font-semibold text-sh-text">{{ $submission->fullName() }}</div>
                                    <div class="mt-0.5">
                                        <a href="mailto:{{ $submission->email }}" class="link-brand text-sm">{{ $submission->email }}</a>
                                    </div>
                                    @if ($submission->phone)
                                        <div class="mt-0.5 text-xs text-sh-mid">
                                            <a href="tel:{{ preg_replace('/\s+/', '', $submission->phone) }}" class="hover:text-brand">{{ $submission->phone }}</a>
                                        </div>
                                    @endif
                                </td>
                                <td class="text-sm text-sh-mid" title="{{ $submission->organisation }}">
                                    {{ $submission->organisation ?: '—' }}
                                </td>
                                <td>
                                    @if ($labels === [])
                                        <span class="text-xs text-sh-mid">—</span>
                                    @else
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach ($labels as $label)
                                                <span class="inline-flex rounded-md border border-[#d7e9f8] bg-[#f7fbff] px-2 py-0.5 text-[11px] font-medium text-sh-mid">{{ $label }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if ($submission->contact_preference === 'yes')
                                        <span class="status-pill status-pill-progress">Yes please</span>
                                    @elseif ($submission->contact_preference === 'information_only')
                                        <span class="status-pill status-pill-muted">Info only</span>
                                    @else
                                        <span class="text-xs text-sh-mid">—</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-xs text-sh-mid">
                                    <div>{{ $submission->created_at?->format('d M Y') }}</div>
                                    <div>{{ $submission->created_at?->format('H:i') }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-sm text-sh-mid">
                                    @if ($search !== '' || $followUpFilter !== 'all')
                                        No submissions match these filters.
                                    @else
                                        No submissions yet. Share the QR or link to start collecting leads.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>{{ $submissions->links() }}</div>
                @if ($submissions->total() > 0)
                    <a href="{{ route('admin.custom-forms.export', array_merge(['custom_form' => $form], $exportParams)) }}" class="btn-brand-outline text-xs">
                        Export {{ $submissions->total() }} {{ $submissions->total() === 1 ? 'row' : 'rows' }} to CSV
                    </a>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
