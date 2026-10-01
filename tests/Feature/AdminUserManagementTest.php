<?php

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('root admin can view and manage admin clinic and registrar accounts', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $root = User::factory()->create([
        'email' => 'root@example.com',
        'role' => 'admin',
        'is_root_admin' => true,
    ]);

    $this->actingAs($root)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/UserManagement')
            ->where('canManageAdmins', true)
            ->has('roleOptions', 3)
        );

    $this->actingAs($root)
        ->post(route('admin.users.store'), [
            'name' => 'Managed Admin',
            'email' => 'managed.admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
            'is_root_admin' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'User account created.');

    $admin = User::query()->where('email', 'managed.admin@example.com')->firstOrFail();

    $this->assertDatabaseHas('users', [
        'email' => 'managed.admin@example.com',
        'role' => 'admin',
        'is_root_admin' => false,
    ]);

    $this->actingAs($root)
        ->delete(route('admin.users.destroy', $admin->user_id))
        ->assertRedirect()
        ->assertSessionHas('success', 'User account deleted.');

    $this->assertSoftDeleted('users', [
        'user_id' => $admin->user_id,
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $root->user_id,
        'action' => 'create',
        'table_name' => 'users',
    ]);
});

test('standard admin can create clinic and registrar users but cannot manage admin users', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $standardAdmin = User::factory()->create([
        'email' => 'standard.admin@example.com',
        'role' => 'admin',
        'is_root_admin' => false,
    ]);

    $targetAdmin = User::factory()->create([
        'email' => 'target.admin@example.com',
        'role' => 'admin',
        'is_root_admin' => false,
    ]);

    $clinic = User::factory()->create([
        'name' => 'Clinic Target',
        'email' => 'clinic.target@example.com',
        'role' => 'clinic',
        'is_root_admin' => false,
    ]);

    $this->actingAs($standardAdmin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/UserManagement')
            ->where('canManageAdmins', false)
            ->has('roleOptions', 2)
        );

    $this->actingAs($standardAdmin)
        ->post(route('admin.users.store'), [
            'name' => 'Clinic Managed',
            'email' => 'clinic.managed@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'clinic',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'User account created.');

    $this->assertDatabaseHas('users', [
        'email' => 'clinic.managed@example.com',
        'role' => 'clinic',
        'is_root_admin' => false,
    ]);

    $this->actingAs($standardAdmin)
        ->post(route('admin.users.store'), [
            'name' => 'Registrar Managed',
            'email' => 'registrar.managed@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'registrar',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'User account created.');

    $this->assertDatabaseHas('users', [
        'email' => 'registrar.managed@example.com',
        'role' => 'registrar',
        'is_root_admin' => false,
    ]);

    $managedClinic = User::query()->where('email', 'clinic.managed@example.com')->firstOrFail();
    $managedRegistrar = User::query()->where('email', 'registrar.managed@example.com')->firstOrFail();

    foreach ([$managedClinic, $managedRegistrar] as $managedUser) {
        $managedUser->forceFill([
            'password' => Hash::make('private-password'),
            'must_change_password' => false,
            'remember_token' => 'existing-token-'.$managedUser->user_id,
        ])->save();

        DB::table('sessions')->insert([
            'id' => 'managed-session-'.$managedUser->user_id,
            'user_id' => $managedUser->user_id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Feature test',
            'payload' => 'test-session-payload',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($standardAdmin)
            ->put(route('admin.users.password.reset-default', $managedUser->user_id))
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    $managedClinic->refresh();
    $managedRegistrar->refresh();

    expect(Hash::check('clinicmanaged', $managedClinic->password))->toBeTrue()
        ->and($managedClinic->must_change_password)->toBeTrue()
        ->and($managedClinic->remember_token)->not->toBe('existing-token-'.$managedClinic->user_id)
        ->and(Hash::check('registrarmanaged', $managedRegistrar->password))->toBeTrue()
        ->and($managedRegistrar->must_change_password)->toBeTrue()
        ->and($managedRegistrar->remember_token)->not->toBe('existing-token-'.$managedRegistrar->user_id);
    $this->assertDatabaseMissing('sessions', ['id' => 'managed-session-'.$managedClinic->user_id]);
    $this->assertDatabaseMissing('sessions', ['id' => 'managed-session-'.$managedRegistrar->user_id]);

    $this->actingAs($standardAdmin)
        ->post(route('admin.users.store'), [
            'name' => 'Blocked Admin',
            'email' => 'blocked.admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ])
        ->assertForbidden();

    $this->actingAs($standardAdmin)
        ->put(route('admin.users.update', $targetAdmin->user_id), [
            'name' => 'Renamed Admin',
            'email' => 'target.admin@example.com',
            'password' => '',
            'role' => 'admin',
            'is_root_admin' => false,
        ])
        ->assertForbidden();

    $this->actingAs($standardAdmin)
        ->put(route('admin.users.password.reset-default', $targetAdmin->user_id))
        ->assertStatus(422);

    $this->actingAs($standardAdmin)
        ->put(route('admin.users.update', $clinic->user_id), [
            'name' => 'Promoted Clinic',
            'email' => 'clinic.target@example.com',
            'password' => '',
            'role' => 'admin',
            'is_root_admin' => false,
        ])
        ->assertForbidden();

    $this->actingAs($standardAdmin)
        ->put(route('admin.users.update', $clinic->user_id), [
            'name' => 'Updated Clinic',
            'email' => 'updated.clinic@example.com',
            'password' => '',
            'role' => 'clinic',
            'is_root_admin' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'User account updated.');

    $this->actingAs($standardAdmin)
        ->delete(route('admin.users.destroy', $targetAdmin->user_id))
        ->assertForbidden();

    $this->assertDatabaseHas('users', [
        'email' => 'target.admin@example.com',
        'name' => $targetAdmin->name,
        'role' => 'admin',
        'is_root_admin' => false,
        'deleted_at' => null,
    ]);

    $this->assertDatabaseHas('users', [
        'email' => 'updated.clinic@example.com',
        'name' => 'Updated Clinic',
        'role' => 'clinic',
        'is_root_admin' => false,
        'deleted_at' => null,
    ]);
});

test('editing a root admin preserves root access when the assignment field is absent', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $root = User::factory()->create([
        'email' => 'only.root@example.com',
        'role' => 'admin',
        'is_root_admin' => true,
    ]);

    $this->actingAs($root)
        ->put(route('admin.users.update', $root->user_id), [
            'name' => 'Only Root',
            'email' => 'only.root@example.com',
            'password' => '',
            'role' => 'admin',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'User account updated.');

    $this->assertDatabaseHas('users', [
        'email' => 'only.root@example.com',
        'is_root_admin' => true,
        'deleted_at' => null,
    ]);
});

test('user management cannot create or promote a root admin', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $root = User::factory()->create([
        'role' => 'admin',
        'is_root_admin' => true,
    ]);
    $admin = User::factory()->create([
        'email' => 'standard.admin@example.com',
        'role' => 'admin',
        'is_root_admin' => false,
    ]);

    $this->actingAs($root)
        ->post(route('admin.users.store'), [
            'name' => 'Blocked Root',
            'email' => 'blocked.root@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
            'is_root_admin' => true,
        ])
        ->assertSessionHasErrors([
            'is_root_admin' => 'Root Admin accounts cannot be created or granted in User Management.',
        ]);

    $this->assertDatabaseMissing('users', [
        'email' => 'blocked.root@example.com',
    ]);

    $this->actingAs($root)
        ->put(route('admin.users.update', $admin->user_id), [
            'name' => $admin->name,
            'email' => $admin->email,
            'password' => '',
            'role' => 'admin',
            'is_root_admin' => true,
        ])
        ->assertSessionHasErrors('is_root_admin');

    expect($admin->fresh()->is_root_admin)->toBeFalse();
});

test('managed account password must be confirmed', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $root = User::factory()->create([
        'role' => 'admin',
        'is_root_admin' => true,
    ]);

    $this->actingAs($root)
        ->post(route('admin.users.store'), [
            'name' => 'Unconfirmed Registrar',
            'email' => 'unconfirmed.registrar@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
            'role' => 'registrar',
        ])
        ->assertSessionHasErrors('password');

    $this->assertDatabaseMissing('users', [
        'email' => 'unconfirmed.registrar@example.com',
    ]);
});
