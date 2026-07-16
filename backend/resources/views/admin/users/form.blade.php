@php
    $isEdit = isset($user) && $user !== null;
@endphp

<form method="POST" action="{{ $isEdit ? route('admin.users.update', $user) : route('admin.users.store') }}" class="space-y-6">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div>
        <x-input-label for="name" value="Name" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name ?? '')" required />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="email" value="Email" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email ?? '')" required />
        <x-input-error :messages="$errors->get('email')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="password" value="{{ $isEdit ? 'New password (optional)' : 'Password' }}" />
        <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" :required="! $isEdit" autocomplete="new-password" />
        <x-input-error :messages="$errors->get('password')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="password_confirmation" value="Confirm password" />
        <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" :required="! $isEdit" autocomplete="new-password" />
    </div>

    <div class="flex items-center gap-2">
        <input id="is_admin" name="is_admin" type="checkbox" value="1" class="rounded border-[#b9d4ef] text-brand shadow-sm focus:ring-brand" @checked(old('is_admin', $user->is_admin ?? false))>
        <label for="is_admin" class="text-sm text-sh-mid">Admin access</label>
    </div>

    <div class="flex items-center gap-3">
        <x-primary-button>{{ $isEdit ? 'Save changes' : 'Create user' }}</x-primary-button>
        <a href="{{ route('admin.users.index') }}" class="link-brand no-underline hover:underline">Cancel</a>
    </div>
</form>
