<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * User management (FR-70) — owner only (user.manage). Each user holds exactly
 * one role (owner / accountant / salesperson). An owner cannot delete their
 * own account, nor remove the last remaining owner.
 */
class UserController extends Controller
{
    public function index()
    {
        return view('shop.user.index', [
            'users' => User::with('roles')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('shop.user.create', ['roles' => $this->roles()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in($this->roles())],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        $user->syncRoles([$data['role']]);

        return redirect()->route('users.index')->with('status', __('ui.common.saved'));
    }

    public function edit(User $user)
    {
        return view('shop.user.edit', [
            'user' => $user->load('roles'),
            'roles' => $this->roles(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', Rule::in($this->roles())],
        ]);

        // Guard against demoting the last owner.
        if ($user->hasRole('owner') && $data['role'] !== 'owner' && $this->ownerCount() <= 1) {
            return back()->withErrors(['role' => __('ui.user.last_owner')]);
        }

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ] + (filled($data['password']) ? ['password' => $data['password']] : []));
        $user->syncRoles([$data['role']]);

        return redirect()->route('users.index')->with('status', __('ui.common.saved'));
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => __('ui.user.no_self_delete')]);
        }
        if ($user->hasRole('owner') && $this->ownerCount() <= 1) {
            return back()->withErrors(['user' => __('ui.user.last_owner')]);
        }

        $user->delete();

        return redirect()->route('users.index')->with('status', __('ui.common.saved'));
    }

    /** @return array<int, string> */
    private function roles(): array
    {
        return Role::orderBy('id')->pluck('name')->all();
    }

    private function ownerCount(): int
    {
        return User::role('owner')->count();
    }
}
