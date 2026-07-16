@props(['mode' => 'create', 'user' => null])

@php
    $isEdit = $mode === 'edit' && $user !== null;
    $restoreOld = old('_form') === ($isEdit ? 'edit' : 'create');
    $nameValue = $restoreOld ? old('name', '') : ($isEdit ? $user->name : '');
    $emailValue = $restoreOld ? old('email', '') : ($isEdit ? $user->email : '');
    $isAdminChecked = $restoreOld ? old('is_admin', false) : ($isEdit ? $user->is_admin : false);
@endphp

<form
    id="user-form"
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
        <x-input-label for="user_name" value="Name" />
        <x-text-input id="user_name" name="name" type="text" class="mt-1 block w-full" :value="$nameValue" required />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="user_email" value="Email" />
        <x-text-input id="user_email" name="email" type="email" class="mt-1 block w-full" :value="$emailValue" required />
        <x-input-error :messages="$errors->get('email')" class="mt-2" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="user_password" value="{{ $isEdit ? 'New password (optional)' : 'Password' }}" />
            <x-text-input id="user_password" name="password" type="password" class="mt-1 block w-full" :required="! $isEdit" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="user_password_confirmation" value="Confirm password" />
            <x-text-input id="user_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" :required="! $isEdit" autocomplete="new-password" />
        </div>
    </div>

    <label class="flex items-center gap-2 rounded-[10px] border border-sh-border bg-sh-surface/60 px-3 py-2.5">
        <input id="user_is_admin" name="is_admin" type="checkbox" value="1" class="rounded border-[#b9d4ef] text-brand shadow-sm focus:ring-brand" @checked($isAdminChecked)>
        <span class="text-sm text-sh-text">Grant admin access to the portal</span>
    </label>
</form>
