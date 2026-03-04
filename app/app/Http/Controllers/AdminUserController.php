<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

class AdminUserController extends Controller
{
    private string $videoRoot = '/videos';

    /**
     * Ensure the user is an admin.
     */
    private function authorizeAdmin()
    {
        if (Auth::user()->role !== Role::Admin->value) {
            abort(403, 'Unauthorized action.');
        }
    }

    /**
     * Display a listing of the users.
     */
    public function index(): Response
    {
        $this->authorizeAdmin();

        $users = User::all();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): Response
    {
        $this->authorizeAdmin();

        return Inertia::render('Admin/Users/Create', []);
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        $password = $request->filled('password')
            ? Hash::make($request->password)
            : null;

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $password,
            'role' => $request->role,
        ]);

        event(new Registered($user));

        return redirect()->route('admin.users.index');
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user): Response
    {
        $this->authorizeAdmin();

        return Inertia::render('Admin/Users/Edit', [
            'user' => $user->load('allowedPaths'),
        ]);
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class.',email,'.$user->id,
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        $user->fill([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
        ]);

        if ($request->filled('password')) {
            $request->validate([
                'password' => ['confirmed', Rules\Password::defaults()],
            ]);
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return redirect()->route('admin.users.index');
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(User $user): RedirectResponse
    {
        $this->authorizeAdmin();

        if ($user->id === Auth::id()) {
            return redirect()->back()->withErrors(['error' => 'You cannot delete yourself.']);
        }

        $user->delete();

        return redirect()->route('admin.users.index');
    }

    /**
     * Show allowed paths editor for user.
     */
    public function editAllowedPaths(Request $request, User $user): Response
    {
        $this->authorizeAdmin();

        $subpath = $request->query('path', '');
        if ($subpath) {
            $subpath = rawurldecode($subpath);
        }

        $fullPath = $this->videoRoot;
        if ($subpath) {
            $fullPath .= '/' . $subpath;
        }

        if (!File::exists($fullPath) || !File::isDirectory($fullPath)) {
            $subpath = '';
            $fullPath = $this->videoRoot;
        }

        $items = [];
        // Scan directories
        $directories = File::directories($fullPath);
        foreach ($directories as $dir) {
            $relativePath = ($subpath ? $subpath . '/' : '') . basename($dir);
            $items[] = [
                'type' => 'folder',
                'name' => basename($dir),
                'path' => $relativePath,
            ];
        }

        // Scan files
        $files = File::files($fullPath);
        foreach ($files as $file) {
            $relativePath = ($subpath ? $subpath . '/' : '') . $file->getFilename();
            $items[] = [
                'type' => 'file',
                'name' => $file->getFilename(),
                'path' => $relativePath,
            ];
        }

        return Inertia::render('Admin/Users/AllowedPaths', [
            'user' => $user->load('allowedPaths'),
            'items' => $items,
            'currentPath' => $subpath,
        ]);
    }

    public function updateAllowedPaths(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin();

        $request->validate([
            'paths' => 'array',
            'paths.*' => 'nullable|string',
        ]);

        $paths = $request->input('paths', []);

        // Remove redundant paths (if parent is selected, remove children)
        sort($paths);
        $filteredPaths = [];
        foreach ($paths as $path) {
            $path = $path ?? ''; // Convert null back to empty string for root
            $path = trim($path, '/');
            $isRedundant = false;
            foreach ($filteredPaths as $alreadyAdded) {
                if ($alreadyAdded === '' || str_starts_with($path, $alreadyAdded . '/')) {
                    $isRedundant = true;
                    break;
                }
            }
            if (!$isRedundant) {
                $filteredPaths[] = $path;
            }
        }

        DB::transaction(function () use ($user, $filteredPaths) {
            $user->allowedPaths()->delete();
            foreach ($filteredPaths as $path) {
                $user->allowedPaths()->create(['path' => $path]);
            }
        });

        return redirect()->route('admin.users.edit', $user->id);
    }
}
