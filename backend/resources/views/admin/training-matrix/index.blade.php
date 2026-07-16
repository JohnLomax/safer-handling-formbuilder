<x-app-layout>
    <x-slot name="header">
        <x-admin.page-header title="Training Matrix" description="Manage organisation course options and pricing shown on the enquiry form.">
            <x-slot name="actions">
                <button type="button" class="btn-brand" x-data @click="$dispatch('open-modal', 'matrix-create-modal')">
                    Add row
                </button>
            </x-slot>
        </x-admin.page-header>
    </x-slot>

    <div class="admin-shell">
        <div class="brand-panel min-w-0 !p-0">
            @include('admin.partials.alerts')

            <div class="border-b border-sh-border/80 px-6 py-4">
                <p class="break-words text-sm text-sh-mid">{{ $entries->total() }} course rows · active rows are served via <code class="whitespace-normal break-all rounded bg-sh-surface px-1.5 py-0.5 text-xs">training_matrix.php</code></p>
            </div>

            <div class="admin-table-wrap !rounded-none !border-0">
                <table class="admin-table admin-table-fixed">
                    <thead>
                        <tr>
                            <th class="w-[14%]">Sector</th>
                            <th class="w-[32%]">Course</th>
                            <th class="w-[12%]">Format</th>
                            <th class="w-[12%]">Style</th>
                            <th class="w-[10%]">Attendees</th>
                            <th class="w-[10%]">Status</th>
                            <th class="w-[10%]">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            <tr>
                                <td class="admin-table-cell-truncate font-medium text-sh-text" title="{{ $entry->sector }}">{{ $entry->sector }}</td>
                                <td class="admin-table-cell-truncate text-sh-mid" title="{{ $entry->course }}">{{ $entry->course }}</td>
                                <td class="admin-table-cell-truncate text-sh-mid" title="{{ $entry->format }}">{{ $entry->format }}</td>
                                <td class="admin-table-cell-truncate text-sh-mid" title="{{ $entry->sub_option }}">{{ $entry->sub_option }}</td>
                                <td class="text-sh-mid">
                                    {{ $entry->min_attendees }}@if($entry->max_cap)–{{ $entry->max_cap }}@endif
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
                                        <x-admin.table-action :href="route('admin.training-matrix.edit', $entry)" icon="edit">
                                            Edit
                                        </x-admin.table-action>
                                        <form action="{{ route('admin.training-matrix.destroy', $entry) }}" method="POST" class="inline" onsubmit="return confirm('Delete this row?')">
                                            @csrf
                                            @method('DELETE')
                                            <x-admin.table-action type="submit" variant="danger" icon="delete">
                                                Delete
                                            </x-admin.table-action>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-sh-mid">No training matrix rows yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4">
                {{ $entries->links() }}
            </div>
        </div>
    </div>

    <x-modal name="matrix-create-modal" maxWidth="4xl" focusable :show="$openCreateModal">
        <div class="admin-modal-header">
            <div>
                <h3 class="text-lg font-semibold text-brand-header">Add training row</h3>
                <p class="mt-1 text-sm text-sh-mid">Create a new organisation course option for the form.</p>
            </div>
            <button type="button" class="btn-icon" x-on:click="$dispatch('close-modal', 'matrix-create-modal')">&times;</button>
        </div>
        <div class="admin-modal-body">
            @include('admin.training-matrix._form', ['mode' => 'create'])
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="btn-brand-outline" x-on:click="$dispatch('close-modal', 'matrix-create-modal')">Cancel</button>
            <button type="submit" form="matrix-create-form" class="btn-brand">Create row</button>
        </div>
    </x-modal>

    @if ($editEntry)
        <x-modal name="matrix-edit-modal" maxWidth="4xl" focusable :show="$openEditModal">
            <div class="admin-modal-header">
                <div>
                    <h3 class="text-lg font-semibold text-brand-header">Edit training row</h3>
                    <p class="mt-1 text-sm text-sh-mid">{{ $editEntry->course }}</p>
                </div>
                <button type="button" class="btn-icon" x-on:click="$dispatch('close-modal', 'matrix-edit-modal')">&times;</button>
            </div>
            <div class="admin-modal-body">
                @include('admin.training-matrix._form', ['mode' => 'edit', 'entry' => $editEntry])
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="btn-brand-outline" x-on:click="$dispatch('close-modal', 'matrix-edit-modal')">Cancel</button>
                <button type="submit" form="matrix-edit-form" class="btn-brand">Save changes</button>
            </div>
        </x-modal>
    @endif
</x-app-layout>
