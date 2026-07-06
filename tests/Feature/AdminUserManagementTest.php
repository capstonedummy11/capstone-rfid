<?php

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'role' => 'registrar',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'User account created.');

    $this->assertDatabaseHas('users', [
        'email' => 'registrar.managed@example.com',
        'role' => 'registrar',
        'is_root_admin' => false,
    ]);

    $this->actingAs($standardAdmin)
        ->post(route('admin.users.store'), [
            'name' => 'Blocked Admin',
            'email' => 'blocked.admin@example.com',
            'password' => 'password123',
            'role' => 'admin',
        ])
        ->assertForbidden();

    $this->actingAs($standardAdmin)
        ->delete(route('admin.users.destroy', $targetAdmin->user_id))
        ->assertForbidden();

    $this->assertDatabaseHas('users', [
        'email' => 'target.admin@example.com',
        'deleted_at' => null,
    ]);
});

test('root admin cannot demote the only root admin account', function () {
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
            'is_root_admin' => false,
        ])
        ->assertStatus(422);

    $this->assertDatabaseHas('users', [
        'email' => 'only.root@example.com',
        'deleted_at' => null,
    ]);
});
