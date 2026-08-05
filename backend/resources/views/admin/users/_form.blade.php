@props(['mode' => 'create', 'user' => null])

@php
    $isEdit = $mode === 'edit' && $user !== null;
    $formId = $isEdit ? 'user-edit-form' : 'user-create-form';
    $fieldPrefix = $isEdit ? 'user_edit' : 'user_create';
    $restoreOld = old('_form') === ($isEdit ? 'edit' : 'create');
    $nameValue = $restoreOld ? old('name', '') : ($isEdit ? $user->name : '');
    $emailValue = $restoreOld ? old('email', '') : ($isEdit ? $user->email : '');
    $isAdminChecked = $restoreOld ? old('is_admin', false) : ($isEdit ? $user->is_admin : false);
@endphp

<form
    id="{{ $formId }}"
    method="POST"
    action="{{ $isEdit ? route('admin.users.update', $user) : route('admin.users.store') }}"
    class="space-y-4"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif
    <input type="hidden" name="_form" value="{{ $isEdit ? 'edit' : 'create' }}">

    <div>
        <x-input-label :for="$fieldPrefix.'_name'" value="Name" />
        <x-text-input :id="$fieldPrefix.'_name'" name="name" type="text" class="mt-1 block w-full" :value="$nameValue" required />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label :for="$fieldPrefix.'_email'" value="Email" />
        <x-text-input :id="$fieldPrefix.'_email'" name="email" type="email" class="mt-1 block w-full" :value="$emailValue" required />
        <x-input-error :messages="$errors->get('email')" class="mt-2" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <x-input-label :for="$fieldPrefix.'_password'" value="{{ $isEdit ? 'New password (optional)' : 'Password' }}" />
            @if ($isEdit)
                <x-text-input :id="$fieldPrefix.'_password'" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
            @else
                <x-text-input :id="$fieldPrefix.'_password'" name="password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
            @endif
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
        <div>
            <x-input-label :for="$fieldPrefix.'_password_confirmation'" value="Confirm password" />
            @if ($isEdit)
                <x-text-input :id="$fieldPrefix.'_password_confirmation'" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
            @else
                <x-text-input :id="$fieldPrefix.'_password_confirmation'" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
            @endif
        </div>
    </div>

    <label class="flex items-center gap-2 rounded-[10px] border border-sh-border bg-sh-surface/60 px-3 py-2.5">
        <input id="{{ $fieldPrefix }}_is_admin" name="is_admin" type="checkbox" value="1" class="rounded border-[#b9d4ef] text-brand shadow-sm focus:ring-brand" @checked($isAdminChecked)>
        <span class="text-sm text-sh-text">Grant admin access to the portal</span>
    </label>
</form>
