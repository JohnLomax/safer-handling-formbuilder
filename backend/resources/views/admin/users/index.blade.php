<x-app-layout>
    <x-slot name="header">
        <x-admin.page-header title="Users" description="Manage who can access the admin portal.">
            <x-slot name="actions">
                <button type="button" class="btn-brand" x-data @click="$dispatch('open-modal', 'user-create-modal')">
                    Add user
                </button>
            </x-slot>
        </x-admin.page-header>
    </x-slot>

    <div class="admin-shell">
        <div class="brand-panel !p-0">
            @include('admin.partials.alerts')

            <div class="border-b border-sh-border/80 px-6 py-4">
                <p class="text-sm text-sh-mid">{{ $users->total() }} user accounts</p>
            </div>

            <div class="admin-table-wrap !rounded-none !border-0">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td class="font-medium text-sh-text">{{ $user->name }}</td>
                                <td class="text-sh-mid">{{ $user->email }}</td>
                                <td>
                                    @if ($user->is_admin)
                                        <span class="status-pill status-pill-submitted">Admin</span>
                                    @else
                                        <span class="status-pill status-pill-progress">User</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="admin-table-actions">
                                        <x-admin.table-action :href="route('admin.users.edit', $user)" icon="edit">
                                            Edit
                                        </x-admin.table-action>
                                        @if (auth()->id() !== $user->id)
                                            <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="inline" onsubmit="return confirm('Delete this user?')">
                                                @csrf
                                                @method('DELETE')
                                                <x-admin.table-action type="submit" variant="danger" icon="delete">
                                                    Delete
                                                </x-admin.table-action>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4">
                {{ $users->links() }}
            </div>
        </div>
    </div>

    <x-modal name="user-create-modal" maxWidth="lg" focusable :show="$openCreateModal">
        <div class="admin-modal-header">
            <div>
                <h3 class="text-lg font-semibold text-brand-header">Add user</h3>
                <p class="mt-1 text-sm text-sh-mid">Create a new admin portal account.</p>
            </div>
            <button type="button" class="btn-icon" x-on:click="$dispatch('close-modal', 'user-create-modal')">&times;</button>
        </div>
        <div class="admin-modal-body">
            @include('admin.users._form', ['mode' => 'create'])
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="btn-brand-outline" x-on:click="$dispatch('close-modal', 'user-create-modal')">Cancel</button>
            <button type="submit" form="user-form" class="btn-brand">Create user</button>
        </div>
    </x-modal>

    @if ($editUser)
        <x-modal name="user-edit-modal" maxWidth="lg" focusable :show="$openEditModal">
            <div class="admin-modal-header">
                <div>
                    <h3 class="text-lg font-semibold text-brand-header">Edit user</h3>
                    <p class="mt-1 text-sm text-sh-mid">Update account details for {{ $editUser->name }}.</p>
                </div>
                <button type="button" class="btn-icon" x-on:click="$dispatch('close-modal', 'user-edit-modal')">&times;</button>
            </div>
            <div class="admin-modal-body">
                @include('admin.users._form', ['mode' => 'edit', 'user' => $editUser])
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="btn-brand-outline" x-on:click="$dispatch('close-modal', 'user-edit-modal')">Cancel</button>
                <button type="submit" form="user-form" class="btn-brand">Save changes</button>
            </div>
        </x-modal>
    @endif
</x-app-layout>
