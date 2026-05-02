<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use App\UseCase\AdminUserUseCase;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly AdminUserUseCase $adminUserUseCase,
    ) {
    }

    public function index(): Response
    {
        $users = $this->adminUserUseCase->list(Auth::user());

        return Inertia::render('Admin/Users/Index', $users->jsonSerialize());
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create', []);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:' . User::class,
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        $payload = $request->only(['name', 'email', 'role']);
        $payload['password'] = $request->filled('password') ? $request->input('password') : null;

        $this->adminUserUseCase->create(Auth::user(), $payload);

        return redirect()->route('admin.users.index');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Admin/Users/Edit', [
            'user' => $user->load('allowedPaths'),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:' . User::class . ',email,' . $user->id,
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        $payload = $request->only(['name', 'email', 'role']);
        $payload['password'] = $request->filled('password') ? $request->input('password') : null;

        if ($request->filled('password')) {
            $request->validate([
                'password' => ['confirmed', Rules\Password::defaults()],
            ]);
        }

        $this->adminUserUseCase->update(Auth::user(), $user, $payload);

        return redirect()->route('admin.users.index');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return redirect()->back()->withErrors(['error' => 'You cannot delete yourself.']);
        }

        $this->adminUserUseCase->delete(Auth::user(), $user);

        return redirect()->route('admin.users.index');
    }

    public function editAllowedPaths(Request $request, User $user): Response
    {
        $paths = $this->adminUserUseCase->editAllowedPaths(
            Auth::user(),
            $user,
            $request->query('path', '')
        );

        return Inertia::render('Admin/Users/AllowedPaths', $paths->jsonSerialize());
    }

    public function updateAllowedPaths(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'paths' => 'array',
            'paths.*' => 'nullable|string',
        ]);

        $this->adminUserUseCase->updateAllowedPaths(Auth::user(), $user, $request->input('paths', []));

        return redirect()->route('admin.users.edit', $user->id);
    }
}
