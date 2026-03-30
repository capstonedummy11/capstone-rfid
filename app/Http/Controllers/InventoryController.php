<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Device;
use App\Models\Inventory;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class InventoryController
{
    public function indexAdmin(Request $request)
    {
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

        return Inertia::render('Auth/Admin/Inventory', [
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

    public function store(Request $request)
    {
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

    public function update(Request $request, int $id)
    {
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

    public function destroy(int $id)
    {
        $inventory = Inventory::findOrFail($id);
        $inventoryId = $inventory->inventory_id;
        $inventory->delete();
        $this->log('delete', 'inventories', 'Deleted inventory record ' . $inventoryId);

        return back()->with('success', 'Inventory deleted successfully.');
    }

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
