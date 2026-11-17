@php
    $mode = $mode ?? 'create';
    $formModel = $formModel ?? null;
    $isEdit = $mode === 'edit' && $formModel !== null;
    $formId = $isEdit ? 'custom-form-edit-form' : 'custom-form-create-form';
    $restoreOld = old('_form') === ($isEdit ? 'edit' : 'create');
    $value = function (string $key, $default = '') use ($restoreOld, $isEdit, $formModel) {
        if ($restoreOld) {
            return old($key, $default);
        }

        return $isEdit ? ($formModel->{$key} ?? $default) : $default;
    };
    $isActiveChecked = $restoreOld
        ? (string) old('is_active', '1') === '1'
        : ($isEdit ? (bool) $formModel->is_active : true);
@endphp

<form
    id="{{ $formId }}"
    method="POST"
    action="{{ $isEdit ? route('admin.custom-forms.update', $formModel) : route('admin.custom-forms.store') }}"
    class="space-y-4"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif
    <input type="hidden" name="_form" value="{{ $isEdit ? 'edit' : 'create' }}">

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="{{ $formId }}_name" value="Internal name" />
            <x-text-input id="{{ $formId }}_name" name="name" type="text" class="mt-1 block w-full" :value="$value('name')" required maxlength="255" placeholder="e.g. BETT 2026 stand" />
            <p class="mt-1 text-xs text-sh-mid">Admin-only label so you can tell events apart.</p>
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="{{ $formId }}_slug" value="URL slug" />
            <x-text-input id="{{ $formId }}_slug" name="slug" type="text" class="mt-1 block w-full font-mono text-sm" :value="$value('slug')" required maxlength="120" placeholder="bett-2026" />
            <p class="mt-1 text-xs text-sh-mid">Public link becomes <code class="rounded bg-sh-surface px-1">/f/your-slug</code>.</p>
            <x-input-error :messages="$errors->get('slug')" class="mt-2" />
        </div>
    </div>

    <div>
        <x-input-label for="{{ $formId }}_title" value="Form title (shown to visitors)" />
        <x-text-input id="{{ $formId }}_title" name="title" type="text" class="mt-1 block w-full" :value="$value('title', 'Tell us what you’re interested in')" required maxlength="255" />
        <x-input-error :messages="$errors->get('title')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="{{ $formId }}_intro" value="Intro text (optional)" />
        <textarea id="{{ $formId }}_intro" name="intro" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand focus:ring-brand" maxlength="2000" placeholder="Thanks for visiting our stand — leave your details and we’ll follow up.">{{ $value('intro') }}</textarea>
        <x-input-error :messages="$errors->get('intro')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="{{ $formId }}_courses_url" value="“Browse courses” link (optional)" />
        <x-text-input id="{{ $formId }}_courses_url" name="courses_url" type="url" class="mt-1 block w-full" :value="$value('courses_url')" maxlength="500" placeholder="https://www.safer-handling.co.uk/" />
        <p class="mt-1 text-xs text-sh-mid">Shown as a light optional link on the form. Leave blank to use the Safer Handling homepage. Avoids dumping the full booking matrix on a QR scan.</p>
        <x-input-error :messages="$errors->get('courses_url')" class="mt-2" />
    </div>

    <div class="flex items-center gap-2">
        <input id="{{ $formId }}_is_active" name="is_active" type="checkbox" value="1" class="rounded border-[#b9d4ef] text-brand shadow-sm focus:ring-brand" @checked($isActiveChecked)>
        <x-input-label for="{{ $formId }}_is_active" value="Active (QR / link accepts submissions)" class="!mb-0" />
    </div>
</form>
