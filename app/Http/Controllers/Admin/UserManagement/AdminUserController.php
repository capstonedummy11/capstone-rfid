<?php

namespace App\Http\Controllers\Admin\UserManagement;

use App\Http\Controllers\Controller;

use App\Http\Resources\RootAuditLogResource;
use App\Http\Resources\RootOverrideResource;
use App\Http\Resources\RootTransferResource;
use App\Models\ActivityLog;
use App\Models\RootAuditLog;
use App\Models\RootOverrideRequest;
use App\Models\RootTransferRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdminUserController extends Controller
{
    private const MANAGED_ROLES = ['admin', 'clinic', 'registrar'];

    // @function index: Ibinabalik ang Auth/Admin/UserManagement page at data para sa request.
    // @useIn index: routes/web.php:351 (users.index)
    /**
     * @feature   User and Role Management
     * @actor     Admin
     * @flow      Dito minamanage ang staff accounts, roles, at password resets.
     * @uses      resources/js/pages/Admin/UserManagement/UserManagementPage.vue; routes/web.php: AdminUserController::index, AdminUserController::store, AdminUserController::update, AdminUserController::resetPassword, AdminUserController::destroy
     * @related   Admin workspace
     * @disable   1) I-comment out ang routes/web.php: AdminUserController::index, AdminUserController::store, AdminUserController::update, AdminUserController::resetPassword, AdminUserController::destroy.
     * @disable   2) Itago ang action sa resources/js/pages/Admin/UserManagement/UserManagementPage.vue; kung may menu link, alisin ito sa resources/js/layouts/AuthNavbar.vue.
     * @disable   3) Ihinto ang app/Http/Controllers/Admin/UserManagement/AdminUserController.php: AdminUserController::index matapos alisin ang routes. Side effect: mawawala ang user and role management.
     */
    public function index(Request $request)
    {
        $actor = $request->user();

        return Inertia::render('Admin/UserManagement/UserManagementPage', [
            'title' => 'User Management',
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
            'rootOwnership' => $this->rootOwnershipPayload($request, $actor),
        ]);
    }

    // @function store: Pinoproseso ang bagong Admin User record.
    // @useIn store: routes/web.php:353 (users.store)
    // Gumagawa ng staff account matapos ang role at Root Admin authorization checks.
    public function store(Request $request)
    {
        $actor = $request->user();
        $this->rejectRootAdminAssignment($request);
        $validated = $this->validatedUser($request);
        $role = strtolower($validated['role']);
        $makeRoot = false;

        $this->authorizeAdminWrite($actor, $role, $makeRoot);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'must_change_password' => true,
            'role' => $role,
            'phone' => $validated['phone'] ?? null,
            'is_root_admin' => $makeRoot,
        ]);

        $this->logActivity($actor, 'create', 'users', 'Created '.$role.' user '.$user->email.'.');

        return back()->with('success', 'User account created.');
    }

    // @function update: Pinoproseso ang pagbabago sa Admin User record.
    // @useIn update: routes/web.php:355 (users.update)
    // Binabago ang staff account habang pinoprotektahan ang Root Admin privileges.
    public function update(Request $request, int $id)
    {
        $actor = $request->user();
        $user = User::query()->findOrFail($id);
        $this->rejectRootAdminAssignment($request);
        $validated = $this->validatedUser($request, $user);
        $role = strtolower($validated['role']);
        $makeRoot = $role === 'admin' && $this->isRootAdmin($user);

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

    // @function resetPassword: Nire-reset ang password sa Admin User flow.
    // @useIn resetPassword: routes/web.php:357 (users.password.reset-default)
    // Ibinabalik ang managed account sa default password at first-login setup.
    public function resetPassword(Request $request, int $id)
    {
        $actor = $request->user();
        $user = User::query()->findOrFail($id);
        $role = strtolower((string) $user->role);

        abort_unless(in_array($role, ['clinic', 'registrar'], true), 422, 'Only Clinic and Registrar passwords can be reset here.');

        $temporaryPassword = $this->defaultPassword($user);

        DB::transaction(function () use ($user, $temporaryPassword) {
            $user->forceFill([
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
                'remember_token' => Str::random(60),
            ])->save();

            DB::table('sessions')
                ->where('user_id', $user->user_id)
                ->delete();
        });

        $this->logActivity($actor, 'update', 'users', 'Reset '.$role.' password for '.$user->email.'.');

        return back()->with(
            'success',
            ucfirst($role).' password reset to '.$temporaryPassword.'. They must create a private password at the next login.'
        );
    }

    // @function destroy: Pinoproseso ang pagtanggal ng Admin User record.
    // @useIn destroy: routes/web.php:360 (users.destroy)
    // Tinatanggal ang pinapayagang account nang hindi nawawala ang huling Root Admin.
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

    // @function validatedUser: Kinukuha ang validated user result para sa Admin User.
    // @useIn validatedUser: AdminUserController::store (app/Http/Controllers/Admin/UserManagement/AdminUserController.php)
    // Nagbabalik ng validated account fields para sa create o update.
    private function validatedUser(Request $request, ?User $user = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')->ignore($user?->user_id, 'user_id')],
            'role' => ['required', Rule::in(self::MANAGED_ROLES)],
            'phone' => ['nullable', 'string', 'max:50'],
        ];

        $rules['password'] = $user
            ? ['nullable', 'string', 'min:8', 'max:255', 'confirmed']
            : ['required', 'string', 'min:8', 'max:255', 'confirmed'];

        return $request->validate($rules);
    }

    // @function rejectRootAdminAssignment: Pinoproseso ang reject root admin assignment para sa Admin User.
    // @useIn rejectRootAdminAssignment: AdminUserController::store (app/Http/Controllers/Admin/UserManagement/AdminUserController.php)
    // Hinaharang ang direct Root Admin assignment sa normal account form.
    private function rejectRootAdminAssignment(Request $request): void
    {
        if ($request->boolean('is_root_admin')) {
            throw ValidationException::withMessages([
                'is_root_admin' => 'Root Admin accounts cannot be created or granted in User Management.',
            ]);
        }
    }

    // @function authorizeAdminWrite: Sini-check ang access sa ang admin write sa Admin User flow.
    // @useIn authorizeAdminWrite: AdminUserController::store (app/Http/Controllers/Admin/UserManagement/AdminUserController.php)
    // Root Admin lang ang puwedeng gumawa o magbago ng Admin privilege.
    private function authorizeAdminWrite(?User $actor, string $role, bool $makeRoot): void
    {
        if (($role === 'admin' || $makeRoot) && ! $this->isRootAdmin($actor)) {
            abort(403, 'Only root admins can create admin accounts.');
        }
    }

    // @function authorizeUserChange: Sini-check ang access sa ang user change sa Admin User flow.
    // @useIn authorizeUserChange: AdminUserController::update (app/Http/Controllers/Admin/UserManagement/AdminUserController.php)
    // Sini-check ang role change at target account bago ito isulat.
    private function authorizeUserChange(?User $actor, User $target, string $newRole, bool $makeRoot): void
    {
        if (($this->isAdmin($target) || $newRole === 'admin' || $makeRoot) && ! $this->isRootAdmin($actor)) {
            abort(403, 'Only root admins can manage admin accounts.');
        }

        if ($this->isRootAdmin($target) && (! $makeRoot || $newRole !== 'admin') && $this->rootAdminCount() <= 1) {
            abort(422, 'At least one root admin account is required.');
        }
    }

    // @function userPayload: Binubuo ang user payload value.
    // @useIn userPayload: AdminUserController::index (app/Http/Controllers/Admin/UserManagement/AdminUserController.php)
    // Binubuo ang user data at allowed actions para sa management page.
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
            'can_reset_password' => in_array(strtolower((string) $user->role), ['clinic', 'registrar'], true),
        ];
    }

    // @function roleOptions: Binubuo ang role options value.
    // @useIn roleOptions: AdminUserController::index (app/Http/Controllers/Admin/UserManagement/AdminUserController.php)
    // Nililimitahan ang available role choices ayon sa current actor.
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

    // @function isAdmin: Sinusuri kung admin para sa Admin User.
    // @useIn isAdmin: AdminUserController::destroy (app/Http/Controllers/Admin/UserManagement/AdminUserController.php)
    // Tinitingnan kung Admin ang account.
    private function isAdmin(?User $user): bool
    {
        return strtolower((string) $user?->role) === 'admin';
    }

    // @function isRootAdmin: Sinusuri kung root admin para sa Admin User.
    // @useIn isRootAdmin: AdminUserController::index (app/Http/Controllers/Admin/UserManagement/AdminUserController.php)
    // Tinitingnan kung Admin ang account at may Root Admin flag.
    private function isRootAdmin(?User $user): bool
    {
        return $this->isAdmin($user) && (bool) $user?->is_root_admin;
    }

    // @function rootAdminCount: Kinukuha ang root admin count result para sa Admin User.
    // @useIn rootAdminCount: AdminUserController::destroy (app/Http/Controllers/Admin/UserManagement/AdminUserController.php)
    // Binibilang ang active Root Admin accounts para sa safety checks.
    private function rootAdminCount(): int
    {
        return User::query()
            ->whereRaw('LOWER(role) = ?', ['admin'])
            ->where('is_root_admin', true)
            ->count();
    }

    // @function defaultPassword: Binubuo ang default password string para sa Admin User.
    // @useIn defaultPassword: AdminUserController::resetPassword (app/Http/Controllers/Admin/UserManagement/AdminUserController.php)
    // Binubuo ang default password mula sa account name.
    private function defaultPassword(User $user): string
    {
        $name = trim($user->name.' '.($user->last_name ?? ''));

        return Str::lower(preg_replace('/\s+/u', '', $name) ?? '');
    }

    // @function rootOwnershipPayload: Binubuo ang root ownership payload value.
    // @useIn rootOwnershipPayload: AdminUserController::index (app/Http/Controllers/Admin/UserManagement/AdminUserController.php)
    // Kinukuha ang pending ownership requests at approval context para sa page.
    private function rootOwnershipPayload(Request $request, ?User $actor): array
    {
        $activeTransfer = RootTransferRequest::query()->with(['fromUser', 'toUser', 'requester'])
            ->whereNotNull('pending_guard')->latest()->first();
        $activeOverride = RootOverrideRequest::query()->with(['fromUser', 'toUser', 'requester', 'approvals.approver'])
            ->whereNotNull('pending_guard')->latest()->first();
        $auditQuery = RootAuditLog::query()->with(['actor', 'target'])->latest();
        if ($action = trim((string) $request->query('root_action'))) {
            $auditQuery->where('action', $action);
        }
        if ($actorEmail = trim((string) $request->query('root_actor'))) {
            $auditQuery->whereHas('actor', fn ($query) => $query->where('email', 'like', "%{$actorEmail}%"));
        }
        if ($from = $request->date('root_from')) {
            $auditQuery->where('created_at', '>=', $from->startOfDay());
        }
        if ($to = $request->date('root_to')) {
            $auditQuery->where('created_at', '<=', $to->endOfDay());
        }

        return [
            'current_user_id' => $actor?->user_id,
            'can_transfer' => $actor?->can('manage-root-ownership') ?? false,
            'can_request_override' => $actor?->can('request-root-override') ?? false,
            'can_approve_override' => $actor?->can('approve-root-override') ?? false,
            'requires_two_factor' => $actor?->hasEnabledTwoFactorAuthentication() ?? false,
            'active_transfer' => $activeTransfer ? RootTransferResource::make($activeTransfer)->resolve($request) : null,
            'active_override' => $activeOverride ? RootOverrideResource::make($activeOverride)->resolve($request) : null,
            'admin_options' => User::query()->where('role', 'admin')->where('is_root_admin', false)->orderBy('name')->get(['user_id', 'name', 'email'])->map(fn ($user) => ['id' => $user->user_id, 'name' => $user->name, 'email' => $user->email]),
            'config' => [
                'transfer_days' => (int) config('root_ownership.transfer_delay_days', 14),
                'cooldown_days' => (int) config('root_ownership.transfer_cooldown_days', 14),
                'override_delay_hours' => (int) config('root_ownership.override_delay_hours', 24),
                'required_approvals' => max(2, (int) config('root_ownership.override_required_approvals', 2)),
            ],
            'audit_logs' => ($actor?->can('manage-root-ownership') ?? false)
                ? RootAuditLogResource::collection($auditQuery->paginate(15, ['*'], 'root_page')->withQueryString())
                : null,
        ];
    }

    // @function logActivity: Nilolog ang activity sa Admin User flow.
    // @useIn logActivity: AdminUserController::store (app/Http/Controllers/Admin/UserManagement/AdminUserController.php)
    // Nagtatala ng activity para sa history at audit.
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
