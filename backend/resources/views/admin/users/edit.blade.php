<x-app-layout>
    <x-slot name="header">
        <h2 class="brand-section-title">Edit user</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="brand-panel">
                @include('admin.partials.alerts')
                @include('admin.users.form', ['user' => $user])
            </div>
        </div>
    </div>
</x-app-layout>
