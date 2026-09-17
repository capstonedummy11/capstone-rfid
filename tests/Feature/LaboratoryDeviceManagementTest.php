<?php

use App\Models\Laboratory;
use App\Models\PanelDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('admin can create and manage a device assigned to a laboratory', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $laboratory = Laboratory::query()->create([
        'name' => 'Computer Laboratory 1',
        'description' => 'Primary room',
        'location' => 'Building A',
        'status' => 'active',
    ]);

    $this->actingAs($admin)->post(route('admin.active-devices.store'), [
        'laboratory_id' => $laboratory->laboratory_id,
        'label' => 'COMLAB-1-PANEL',
        'description' => 'Front terminal',
        'pin' => '2468',
        'is_active' => true,
    ])->assertRedirect();

    $device = PanelDevice::query()->where('label', 'COMLAB-1-PANEL')->firstOrFail();
    expect($device->laboratory_id)->toBe($laboratory->laboratory_id)
        ->and($device->is_active)->toBeTrue()
        ->and(Hash::check('2468', $device->pin_hash))->toBeTrue();

    $this->actingAs($admin)->put(route('admin.active-devices.update', $device), [
        'laboratory_id' => $laboratory->laboratory_id,
        'label' => $device->label,
        'description' => $device->description,
        'is_active' => false,
    ])->assertRedirect();

    expect($device->fresh()->is_active)->toBeFalse();
});

test('only one managed device can be assigned to a laboratory', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $laboratory = Laboratory::query()->create([
        'name' => 'Computer Laboratory 2',
        'location' => 'Building B',
        'status' => 'active',
    ]);
    PanelDevice::query()->create([
        'laboratory_id' => $laboratory->laboratory_id,
        'label' => 'FIRST-PANEL',
        'pin_hash' => Hash::make('1234'),
        'is_active' => true,
    ]);

    $this->actingAs($admin)->post(route('admin.active-devices.store'), [
        'laboratory_id' => $laboratory->laboratory_id,
        'label' => 'SECOND-PANEL',
        'pin' => '5678',
        'is_active' => true,
    ])->assertSessionHasErrors('laboratory_id');
});

test('attendance panel uses the pin of the device assigned to the selected laboratory', function () {
    $laboratory = Laboratory::query()->create([
        'name' => 'Managed Laboratory',
        'location' => 'Building C',
        'status' => 'active',
    ]);
    PanelDevice::query()->create([
        'laboratory_id' => $laboratory->laboratory_id,
        'label' => 'MANAGED-LAB-PANEL',
        'pin_hash' => Hash::make('8642'),
        'is_active' => true,
    ]);

    $this->postJson(route('panelVerify'), [
        'room' => $laboratory->name,
        'pin' => '8642',
    ])->assertOk()->assertJson(['success' => true]);

    $this->postJson(route('attendanceControlPanel.room'), [
        'room' => $laboratory->name,
    ])->assertOk();

    $this->assertDatabaseHas('rfid_panel_sessions', [
        'room' => $laboratory->name,
        'panel_id' => 'MANAGED-LAB-PANEL',
        'status' => 'online',
    ]);
});

test('disabled managed device blocks attendance panel login without using fallback pin', function () {
    $laboratory = Laboratory::query()->create([
        'name' => 'Disabled Device Laboratory',
        'location' => 'Building D',
        'status' => 'active',
    ]);
    PanelDevice::query()->create([
        'laboratory_id' => $laboratory->laboratory_id,
        'label' => 'DISABLED-LAB-PANEL',
        'pin_hash' => Hash::make('9753'),
        'is_active' => false,
    ]);

    $this->postJson(route('panelVerify'), [
        'room' => $laboratory->name,
        'pin' => '1234',
    ])->assertForbidden()->assertJson([
        'success' => false,
        'message' => 'The attendance device assigned to this laboratory is disabled.',
    ]);
});
