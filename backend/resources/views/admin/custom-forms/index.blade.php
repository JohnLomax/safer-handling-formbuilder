<x-app-layout>
    <x-slot name="header">
        <x-admin.page-header title="Custom Forms" description="QR / link forms for events and stands — capture contact details and training interests without the full booking flow.">
            <x-slot name="actions">
                <button type="button" class="btn-brand" x-data @click="$dispatch('open-modal', 'custom-form-create-modal')">
                    New custom form
                </button>
            </x-slot>
        </x-admin.page-header>
    </x-slot>

    <div class="admin-shell">
        <div class="brand-panel min-w-0 !p-0">
            @include('admin.partials.alerts')

            <div class="border-b border-sh-border/80 px-6 py-4">
                <p class="text-sm text-sh-mid">{{ $forms->total() }} forms · each has a public URL and QR code for visitors to scan.</p>
            </div>

            <div class="admin-table-wrap !rounded-none !border-0">
                <table class="admin-table admin-table-fixed">
                    <thead>
                        <tr>
                            <th class="w-[22%]">Name</th>
                            <th class="w-[18%]">Slug</th>
                            <th class="w-[28%]">Title</th>
                            <th class="w-[10%]">Submissions</th>
                            <th class="w-[10%]">Status</th>
                            <th class="w-[12%]">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($forms as $entry)
                            <tr>
                                <td class="font-semibold text-sh-text">
                                    <a href="{{ route('admin.custom-forms.show', $entry) }}" class="link-brand">{{ $entry->name }}</a>
                                </td>
                                <td class="font-mono text-xs text-sh-mid">/f/{{ $entry->slug }}</td>
                                <td class="admin-table-cell-truncate text-sh-mid" title="{{ $entry->title }}">{{ $entry->title }}</td>
                                <td>
                                    <a href="{{ route('admin.custom-forms.show', $entry) }}" class="link-brand text-sm font-semibold">
                                        {{ $entry->submissions_count }}
                                    </a>
                                </td>
                                <td>
                                    @if ($entry->is_active)
                                        <span class="status-pill status-pill-success">Active</span>
                                    @else
                                        <span class="status-pill status-pill-muted">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="admin-table-actions">
                                        <x-admin.table-action
                                            :href="route('admin.custom-forms.show', $entry)"
                                            icon="eye"
                                            title="View / QR"
                                            aria-label="View"
                                        />
                                        <x-admin.table-action
                                            :href="route('admin.custom-forms.edit', $entry)"
                                            icon="edit"
                                            title="Edit"
                                            aria-label="Edit"
                                        />
                                        <form action="{{ route('admin.custom-forms.destroy', $entry) }}" method="POST" class="inline" onsubmit="return confirm('Delete this form and all its submissions?');">
                                            @csrf
                                            @method('DELETE')
                                            <x-admin.table-action
                                                type="submit"
                                                variant="danger"
                                                icon="delete"
                                                title="Delete"
                                                aria-label="Delete"
                                            />
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-sh-mid">No custom forms yet. Create one for an event stand or QR flyer.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4">
                {{ $forms->links() }}
            </div>
        </div>
    </div>

    <x-modal name="custom-form-create-modal" maxWidth="3xl" focusable :show="$openCreateModal">
        <div class="admin-modal-header">
            <div>
                <h3 class="text-lg font-semibold text-brand-header">New custom form</h3>
                <p class="mt-1 text-sm text-sh-mid">Create a QR-friendly lead capture form.</p>
            </div>
            <button type="button" class="btn-icon" x-on:click="$dispatch('close-modal', 'custom-form-create-modal')">&times;</button>
        </div>
        <div class="admin-modal-body">
            @include('admin.custom-forms._form', ['mode' => 'create'])
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="btn-brand-outline" x-on:click="$dispatch('close-modal', 'custom-form-create-modal')">Cancel</button>
            <button type="submit" form="custom-form-create-form" class="btn-brand">Create form</button>
        </div>
    </x-modal>

    @if ($editForm)
        <x-modal name="custom-form-edit-modal" maxWidth="3xl" focusable :show="$openEditModal">
            <div class="admin-modal-header">
                <div>
                    <h3 class="text-lg font-semibold text-brand-header">Edit custom form</h3>
                    <p class="mt-1 text-sm text-sh-mid">{{ $editForm->name }}</p>
                </div>
                <button type="button" class="btn-icon" x-on:click="$dispatch('close-modal', 'custom-form-edit-modal')">&times;</button>
            </div>
            <div class="admin-modal-body">
                @include('admin.custom-forms._form', ['mode' => 'edit', 'formModel' => $editForm])
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="btn-brand-outline" x-on:click="$dispatch('close-modal', 'custom-form-edit-modal')">Cancel</button>
                <button type="submit" form="custom-form-edit-form" class="btn-brand">Save changes</button>
            </div>
        </x-modal>
    @endif
</x-app-layout>
