<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Models\ActivityLog;
use App\Models\Device;
use App\Models\Inventory;
use App\Models\SystemSetting;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class InventoryController
{
    // @function indexAdmin: Ibinabalik ang Auth/Admin/Inventory page at data para sa request.
    // @useIn indexAdmin: routes/web.php:339 (inventory.index)
    /**
     * WHAT IT DOES: Dating pahina ito para sa school inventory; hindi na ito ginagamit.
     * WHO USES IT: Wala sa kasalukuyang school workflow.
     * WHAT HAPPENS: Nananatili ang lumang records, pero hindi na dapat gamitin ang feature.
     * WARNING: Needs developer check bago ito muling buksan.
    */
    public function indexAdmin(Request $request)
    {
        if (! SystemSetting::boolean(SystemSetting::INVENTORY_ENABLED, false)) {
            return redirect()->route('admin.settings.edit')->with('success', 'Inventory is currently disabled.');
        }

        $filters = [
            'search' => trim((string) $request->input('search', '')),
        ];

        $query = Inventory::query()->with('item');

        if ($filters['search'] !== '') {
            $term = $filters['search'];
            $query->whereHas('item', function ($itemQuery) use ($term) {
                $itemQuery
                    ->where('item_name', 'like', "%{$term}%")
                    ->orWhere('item_sku', 'like', "%{$term}%")
                    ->orWhere('item_barcode', 'like', "%{$term}%");
            });
        }

        return Inertia::render('Admin/Inventory/InventoryPage', [
            'title' => 'Inventory',
            'inventories' => $query->orderByDesc('updated_at')->get()->map(fn(Inventory $inventory) => [
                'inventory_id' => $inventory->inventory_id,
                'item_id' => $inventory->item_id,
                'item_name' => $inventory->item?->item_name,
                'item_code' => $inventory->item?->item_code,
                'item_sku' => $inventory->item?->item_sku,
                'item_barcode' => $inventory->item?->item_barcode ?: $inventory->item?->barcode,
                'quantity' => $inventory->quantity,
                'updated_at' => $inventory->updated_at?->format('Y-m-d H:i:s'),
            ])->values(),
            'filters' => $filters,
            'itemOptions' => Device::query()->orderBy('item_name')->get(['item_id', 'item_name', 'item_sku'])->map(fn(Device $item) => [
                'item_id' => $item->item_id,
                'label' => trim(($item->item_name ?? 'Item') . ' - ' . ($item->item_sku ?? 'N/A')),
            ])->values(),
        ]);
    }

    // @function store: Pinoproseso ang bagong Inventory record.
    // @useIn store: routes/web.php:341 (inventory.store)
    public function store(Request $request)
    {
        abort_unless(SystemSetting::boolean(SystemSetting::INVENTORY_ENABLED, false), 423, 'Inventory is currently disabled.');

        $validated = $request->validate([
            'item_id' => 'required|exists:items,item_id|unique:inventories,item_id',
            'quantity' => 'required|integer|min:0',
            'transaction_type' => 'nullable|string|max:255',
        ]);

        $inventory = Inventory::create([
            'item_id' => $validated['item_id'],
            'quantity' => $validated['quantity'],
        ]);

        Transaction::create([
            'inventory_id' => $inventory->inventory_id,
            'item_id' => $inventory->item_id,
            'quantity' => $inventory->quantity,
            'transaction_type' => $validated['transaction_type'] ?: 'initial_stock',
        ]);

        $this->log('create', 'inventories', 'Created inventory record ' . $inventory->inventory_id);

        return back()->with('success', 'Inventory added successfully.');
    }

    // @function update: Pinoproseso ang pagbabago sa Inventory record.
    // @useIn update: routes/web.php:343 (inventory.update)
    public function update(Request $request, int $id)
    {
        abort_unless(SystemSetting::boolean(SystemSetting::INVENTORY_ENABLED, false), 423, 'Inventory is currently disabled.');

        $inventory = Inventory::findOrFail($id);

        $validated = $request->validate([
            'item_id' => 'required|exists:items,item_id|unique:inventories,item_id,' . $id . ',inventory_id',
            'quantity' => 'required|integer|min:0',
            'transaction_type' => 'nullable|string|max:255',
        ]);

        $previousQuantity = $inventory->quantity;
        $inventory->update([
            'item_id' => $validated['item_id'],
            'quantity' => $validated['quantity'],
        ]);

        if ($previousQuantity !== (int) $validated['quantity']) {
            Transaction::create([
                'inventory_id' => $inventory->inventory_id,
                'item_id' => $inventory->item_id,
                'quantity' => abs((int) $validated['quantity'] - (int) $previousQuantity),
                'transaction_type' => $validated['transaction_type'] ?: ((int) $validated['quantity'] > (int) $previousQuantity ? 'restock' : 'deduction'),
            ]);
        }

        $this->log('update', 'inventories', 'Updated inventory record ' . $inventory->inventory_id);

        return back()->with('success', 'Inventory updated successfully.');
    }

    // @function destroy: Pinoproseso ang pagtanggal ng Inventory record.
    // @useIn destroy: routes/web.php:345 (inventory.destroy)
    public function destroy(int $id)
    {
        abort_unless(SystemSetting::boolean(SystemSetting::INVENTORY_ENABLED, false), 423, 'Inventory is currently disabled.');

        $inventory = Inventory::findOrFail($id);
        $inventoryId = $inventory->inventory_id;
        $inventory->delete();
        $this->log('delete', 'inventories', 'Deleted inventory record ' . $inventoryId);

        return back()->with('success', 'Inventory deleted successfully.');
    }

    // @function log: Nilolog ang inventory sa Inventory flow.
    // @useIn log: InventoryController::store (app/Http/Controllers/Admin/Inventory/InventoryController.php)
    private function log(string $action, string $tableName, string $description): void
    {
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'table_name' => $tableName,
            'description' => $description,
        ]);
    }
}
