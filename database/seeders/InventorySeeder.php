<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\Inventory;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $items = Device::query()->get(['item_id']);

        foreach ($items as $item) {
            Inventory::updateOrCreate(
                ['item_id' => $item->item_id],
                ['quantity' => 0],
            );
        }
    }
}