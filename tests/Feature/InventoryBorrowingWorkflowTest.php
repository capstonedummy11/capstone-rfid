<?php

use App\Models\Item;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('inventory item created by admin is available on the borrowing page', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    SystemSetting::setBoolean(SystemSetting::INVENTORY_ENABLED, true);
    SystemSetting::setBoolean(SystemSetting::BORROWING_ENABLED, true);

    $this->actingAs($admin)
        ->post(route('admin.items.store'), [
            'barcode' => 'CROSS-PAGE-ITEM-001',
            'name' => 'Cross Page RFID Reader',
            'sku' => 'RFID-READER-001',
            'description' => 'USB RFID reader for the shared inventory workflow.',
            'status' => 'Available',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Item added successfully.');

    $item = Item::query()->where('barcode', 'CROSS-PAGE-ITEM-001')->firstOrFail();
    $this->assertDatabaseHas('inventory_items', [
        'item_id' => $item->item_id,
        'barcode' => 'CROSS-PAGE-ITEM-001',
        'status' => 'Available',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.inventory'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Inventory/InventoryPage')
            ->has('items', 1)
            ->where('items.0.item_id', $item->item_id)
            ->where('items.0.barcode', 'CROSS-PAGE-ITEM-001')
            ->where('items.0.name', 'Cross Page RFID Reader')
        );

    $this->actingAs($admin)
        ->get(route('admin.borrow'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Borrow/BorrowPage')
            ->has('borrowItemsCatalog', 1)
            ->where('borrowItemsCatalog.0.name', 'Cross Page RFID Reader')
            ->where('borrowItemsCatalog.0.id', 'RFID-READER-001')
            ->where('borrowItemsCatalog.0.barcode', 'CROSS-PAGE-ITEM-001')
            ->where('borrowItemsCatalog.0.status', 'Available')
        );
});
