<?php
// FEATURE:inventory-management - konektadong model, service, route, o UI para sa feature na ito.

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class ItemController extends Controller
{
    // @function store: Pinoproseso ang bagong Item record.
    // @useIn store: routes/web.php:449 (items.store)
    public function store(Request $request)
    {
        abort_unless(SystemSetting::boolean(SystemSetting::INVENTORY_ENABLED, false), 423, 'Inventory is currently disabled.');

        $request->validate([
            'barcode' => ['required', 'string', Rule::unique('inventory_items', 'barcode')->whereNull('deleted_at')],
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'status' => 'nullable|string|max:255',
        ]);

        Item::create([
            'barcode' => $request->barcode,
            'name' => $request->name,
            'sku' => $request->sku,
            'description' => $request->description,
            'status' => $request->status ?? 'Available',
        ]);

        return back()->with('success', 'Item added successfully.');
    }

    // @function update: Pinoproseso ang pagbabago sa Item record.
    // @useIn update: routes/web.php:447 (items.update)
    public function update(Request $request, Item $item)
    {
        abort_unless(SystemSetting::boolean(SystemSetting::INVENTORY_ENABLED, false), 423, 'Inventory is currently disabled.');

        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:255', Rule::unique('inventory_items', 'barcode')->whereNull('deleted_at')->ignore($item->item_id, 'item_id')],
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:255',
            'status' => 'required|string|max:255',
        ]);

        $item->update($validated);

        return back();
    }

    // @function destroy: Pinoproseso ang pagtanggal ng Item record.
    // @useIn destroy: routes/web.php:448 (items.destroy)
    public function destroy(Item $item)
    {
        abort_unless(SystemSetting::boolean(SystemSetting::INVENTORY_ENABLED, false), 423, 'Inventory is currently disabled.');

        $item->delete();

        return back()->with('success', 'Item deleted successfully.');
    }
}
