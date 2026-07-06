<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminUserController extends Controller
{
    private const MANAGED_ROLES = ['admin', 'clinic', 'registrar'];

    public function index(Request $request)
    {
        $actor = $request->user();

        return Inertia::render('Auth/Admin/UserManagement', [
            'users' => User::query()
                ->whereIn('role', self::MANAGED_ROLES)
                ->orderByRaw("CASE LOWER(role) WHEN 'admin' THEN 1 WHEN 'clinic' THEN 2 WHEN 'registrar' THEN 3 ELSE 4 END")
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => $this->userPayload($user, $actor))
                ->values(),
            'roleOptions' => $this->roleOptions($actor),
            'canManageAdmins' => $this->isRootAdmin($actor),
            'stats' => [
                'admins' => User::query()->whereRaw('LOWER(role) = ?', ['admin'])->count(),
                'rootAdmins' => User::query()->whereRaw('LOWER(role) = ?', ['admin'])->where('is_root_admin', true)->count(),
                'clinic' => User::query()->whereRaw('LOWER(role) = ?', ['clinic'])->count(),
                'registrars' => User::query()->whereRaw('LOWER(role) = ?', ['registrar'])->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $actor = $request->user();
        $validated = $this->validatedUser($request);
        $role = strtolower($validated['role']);
        $makeRoot = $role === 'admin' && $request->boolean('is_root_admin');

        $this->authorizeAdminWrite($actor, $role, $makeRoot);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $role,
            'phone' => $validated['phone'] ?? null,
            'is_root_admin' => $makeRoot,
        ]);

        $this->logActivity($actor, 'create', 'users', 'Created '.$role.' user '.$user->email.'.');

        return back()->with('success', 'User account created.');
    }

    public function update(Request $request, int $id)
    {
        $actor = $request->user();
        $user = User::query()->findOrFail($id);
        $validated = $this->validatedUser($request, $user);
        $role = strtolower($validated['role']);
        $makeRoot = $role === 'admin' && $request->boolean('is_root_admin');

        $this->authorizeUserChange($actor, $user, $role, $makeRoot);

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $role,
            'phone' => $validated['phone'] ?? null,
            'is_root_admin' => $makeRoot,
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
        }

        $user->update($payload);

        $this->logActivity($actor, 'update', 'users', 'Updated '.$role.' user '.$user->email.'.');

        return back()->with('success', 'User account updated.');
    }

    public function destroy(Request $request, int $id)
    {
        $actor = $request->user();
        $user = User::query()->findOrFail($id);

        abort_if($actor?->user_id === $user->user_id, 422, 'You cannot delete your own account.');

        if ($this->isAdmin($user) && ! $this->isRootAdmin($actor)) {
            abort(403, 'Only root admins can delete admin accounts.');
        }

        if ($this->isRootAdmin($user) && $this->rootAdminCount() <= 1) {
            abort(422, 'At least one root admin account is required.');
        }

        $email = $user->email;
        $role = strtolower((string) $user->role);
        $user->delete();

        $this->logActivity($actor, 'delete', 'users', 'Deleted '.$role.' user '.$email.'.');

        return back()->with('success', 'User account deleted.');
    }

    private function validatedUser(Request $request, ?User $user = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->user_id, 'user_id')],
            'role' => ['required', Rule::in(self::MANAGED_ROLES)],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_root_admin' => ['nullable', 'boolean'],
        ];

        $rules['password'] = $user
            ? ['nullable', 'string', 'min:8', 'max:255']
            : ['required', 'string', 'min:8', 'max:255'];

        return $request->validate($rules);
    }

    private function authorizeAdminWrite(?User $actor, string $role, bool $makeRoot): void
    {
        if (($role === 'admin' || $makeRoot) && ! $this->isRootAdmin($actor)) {
            abort(403, 'Only root admins can create admin accounts.');
        }
    }

    private function authorizeUserChange(?User $actor, User $target, string $newRole, bool $makeRoot): void
    {
        if (($this->isAdmin($target) || $newRole === 'admin' || $makeRoot) && ! $this->isRootAdmin($actor)) {
            abort(403, 'Only root admins can manage admin accounts.');
        }

        if ($this->isRootAdmin($target) && (! $makeRoot || $newRole !== 'admin') && $this->rootAdminCount() <= 1) {
            abort(422, 'At least one root admin account is required.');
        }
    }

    private function userPayload(User $user, ?User $actor): array
    {
        $actorIsRoot = $this->isRootAdmin($actor);
        $targetIsAdmin = $this->isAdmin($user);

        return [
            'id' => $user->user_id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => strtolower((string) $user->role),
            'is_root_admin' => (bool) $user->is_root_admin,
            'created_at' => optional($user->created_at)->format('Y-m-d H:i'),
            'can_update' => ! $targetIsAdmin || $actorIsRoot,
            'can_delete' => $actor?->user_id !== $user->user_id && (! $targetIsAdmin || $actorIsRoot),
        ];
    }

    private function roleOptions(?User $actor): array
    {
        return collect(self::MANAGED_ROLES)
            ->filter(fn (string $role) => $role !== 'admin' || $this->isRootAdmin($actor))
            ->map(fn (string $role) => [
                'value' => $role,
                'label' => ucfirst($role),
            ])
            ->values()
            ->all();
    }

    private function isAdmin(?User $user): bool
    {
        return strtolower((string) $user?->role) === 'admin';
    }

    private function isRootAdmin(?User $user): bool
    {
        return $this->isAdmin($user) && (bool) $user?->is_root_admin;
    }

    private function rootAdminCount(): int
    {
        return User::query()
            ->whereRaw('LOWER(role) = ?', ['admin'])
            ->where('is_root_admin', true)
            ->count();
    }

    private function logActivity(?User $actor, string $action, string $tableName, string $description): void
    {
        ActivityLog::query()->create([
            'user_id' => $actor?->user_id,
            'action' => $action,
            'table_name' => $tableName,
            'description' => $description,
        ]);
    }
}
