<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Ported from backend/admin/manage_users_func.php. Hierarchy rule: you may only manage users with a strictly higher (less privileged) access_level than your own. */
class ManageUsersController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'access_level' => ['nullable', 'integer', Rule::in([0, 1, 2, 3])],
        ]);

        $query = User::with('role')->orderBy('username');
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('contact_number', 'like', "%{$search}%");
            });
        }

        if (isset($filters['access_level'])) {
            $query->where('access_level', $filters['access_level']);
        }

        $users = $query->paginate(10)->withQueryString();

        if ($request->header('X-Live-Filter') === 'true') {
            return view('users.partials.results', compact('users'));
        }

        return view('users.index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'access_level' => $filters['access_level'] ?? '',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $currentLevel = Auth::user()->access_level;

        $validated = $request->validate([
            'username' => ['required', 'string', 'min:4', 'regex:/^[a-zA-Z0-9_]+$/', 'unique:users,username'],
            'firstName' => ['required', 'string', 'max:50'],
            'lastName' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'contactNumber' => ['required', 'string', 'regex:/^[0-9]{7,20}$/'],
            'accessLevel' => ['required', 'integer', Rule::in([0, 1, 2, 3])],
        ]);

        $accessLevel = (int) $validated['accessLevel'];
        if ($currentLevel > $accessLevel) {
            return back()->with('error', 'You do not have permission to assign this access level.')->withInput();
        }

        $userRole = Role::firstOrCreate(['role_name' => 'User']);

        User::create([
            'username' => trim($validated['username']),
            'first_name' => trim($validated['firstName']),
            'last_name' => trim($validated['lastName']),
            'email' => trim($validated['email']),
            'password' => Hash::make($validated['password']),
            'contact_number' => $validated['contactNumber'],
            'role_id' => $userRole->role_id,
            'access_level' => $accessLevel,
        ]);

        AuditLogger::log('Added new user', 'User', 'Added new user');

        return back()->with('success', 'User added successfully');
    }

    public function update(Request $request, User $user)
    {
        $currentLevel = Auth::user()->access_level;
        $canManageAccessLevel = $currentLevel <= 1;

        $rules = [
            'username' => ['required', 'string', 'min:4', 'regex:/^[a-zA-Z0-9_]+$/', Rule::unique('users', 'username')->ignore($user->user_id, 'user_id')],
            'firstName' => ['required', 'string', 'max:50'],
            'lastName' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->user_id, 'user_id')],
            'contactNumber' => ['required', 'string', 'regex:/^[0-9]{7,20}$/'],
        ];

        if ($canManageAccessLevel) {
            $rules['accessLevel'] = ['required', 'integer', Rule::in([0, 1, 2, 3])];
        }

        $validated = $request->validate($rules);
        $accessLevel = $canManageAccessLevel ? (int) $validated['accessLevel'] : $user->access_level;

        if ($currentLevel >= $user->access_level) {
            return back()->with('error', 'You do not have permission to edit this user.');
        }
        if ($canManageAccessLevel && $currentLevel > $accessLevel) {
            return back()->with('error', 'You do not have permission to assign this access level.');
        }

        $userRole = Role::firstOrCreate(['role_name' => 'User']);

        $user->update([
            'username' => trim($validated['username']),
            'first_name' => trim($validated['firstName']),
            'last_name' => trim($validated['lastName']),
            'email' => trim($validated['email']),
            'contact_number' => $validated['contactNumber'],
            'role_id' => $userRole->role_id,
            'access_level' => $accessLevel,
        ]);

        AuditLogger::log('Edited information of user', 'User', 'Edited information of user');

        return back()->with('success', 'User updated successfully');
    }

    public function destroy(User $user)
    {
        $currentUser = Auth::user();

        if ($user->user_id === $currentUser->user_id) {
            return back()->with('error', 'You cannot delete your own account');
        }
        if ($currentUser->access_level >= $user->access_level) {
            return back()->with('error', 'You do not have permission to delete this user.');
        }

        $deletedUsername = $user->username;
        $user->delete();

        AuditLogger::log('Deleted user', 'User', "{$deletedUsername} is removed");

        return back()->with('success', 'User deleted successfully');
    }
}
