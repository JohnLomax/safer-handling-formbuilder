<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $editUser = null;
        if (request('modal') === 'edit' && request()->filled('user')) {
            $editUser = User::query()->find(request('user'));
        }

        return view('admin.users.index', [
            'users' => User::query()->orderBy('name')->paginate(15),
            'editUser' => $editUser,
            'openCreateModal' => request('modal') === 'create',
            'openEditModal' => request('modal') === 'edit' && $editUser !== null,
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('admin.users.index', ['modal' => 'create']);
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_admin' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('admin.users.index', ['modal' => 'create'])
                ->withInput()
                ->withErrors($validator);
        }

        $validated = $validator->validated();

        User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_admin' => $request->boolean('is_admin'),
            'email_verified_at' => now(),
        ]);

        return redirect()->route('admin.users.index')->with('status', 'User created.');
    }

    public function edit(User $user): RedirectResponse
    {
        return redirect()->route('admin.users.index', ['modal' => 'edit', 'user' => $user->id]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'is_admin' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('admin.users.index', ['modal' => 'edit', 'user' => $user->id])
                ->withInput()
                ->withErrors($validator);
        }

        $validated = $validator->validated();

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->is_admin = $request->boolean('is_admin');

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()->route('admin.users.index')->with('status', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->id === $user->id) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'User deleted.');
    }
}
