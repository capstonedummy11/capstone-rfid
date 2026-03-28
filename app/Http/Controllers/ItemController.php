<?php

namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use App\Models\Item;

class ItemController extends Controller
{
    public function store(Request $request) 
    {
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
            'status'
        ]);

        return back()->with('success', 'Item added successfully.');
    }
}
