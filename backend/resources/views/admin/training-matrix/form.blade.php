<x-app-layout>
    <x-slot name="header">
        <h2 class="brand-section-title">
            {{ $entry->exists ? 'Edit training matrix row' : 'Add training matrix row' }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="brand-panel">
                @include('admin.partials.alerts')

                @include('admin.training-matrix._form', [
                    'mode' => $entry->exists ? 'edit' : 'create',
                    'entry' => $entry,
                ])

                <div class="mt-6 flex items-center gap-3">
                    <button type="submit" form="{{ $entry->exists ? 'matrix-edit-form' : 'matrix-create-form' }}" class="btn-brand">
                        {{ $entry->exists ? 'Save changes' : 'Create row' }}
                    </button>
                    <a href="{{ route('admin.training-matrix.index') }}" class="link-brand no-underline hover:underline">Cancel</a>
                </div>
            </div>
        </div>
    </div>

    <x-field-help-modal />
</x-app-layout>
