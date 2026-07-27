<?php

namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use App\Models\Item;
use App\Models\SystemSetting;

class ItemController extends Controller
{
    public function store(Request $request) 
    {
        abort_unless(SystemSetting::boolean(SystemSetting::INVENTORY_ENABLED, false), 423, 'Inventory is currently disabled.');

        $request->validate([
            'barcode'          => 'required|string|unique:inventory_items,barcode',
            'name'        => 'required|string|max:255',
            'sku'         => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'status' => 'nullable|string|max:255'
        ]);

        Item::create([
            'barcode'     => $request->barcode,
            'name'        => $request->name,
            'sku'         => $request->sku,
            'description' => $request->description,
            'status' => $request->status ?? 'Available',
        ]);

        return back()->with('success', 'Item added successfully.');
    }
    public function update(Request $request, Item $item)
    {
        abort_unless(SystemSetting::boolean(SystemSetting::INVENTORY_ENABLED, false), 423, 'Inventory is currently disabled.');

        $validated = $request->validate([
            'barcode'     => 'required|string|max:255',
            'name'        => 'required|string|max:255',
            'sku'         => 'nullable|string|max:255',
            'description' => 'nullable|string|max:255',
            'status' => 'required|string|max:255',
        ]);

        $item->update($validated);

        return back();
    }
    public function destroy(Item $item)
    {
        abort_unless(SystemSetting::boolean(SystemSetting::INVENTORY_ENABLED, false), 423, 'Inventory is currently disabled.');

        $item->delete();
        return back()->with('success', 'Item deleted successfully.');
    }
}
