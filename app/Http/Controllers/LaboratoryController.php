<?php

namespace App\Http\Controllers;

use App\Models\Laboratory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LaboratoryController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Display admin listing of the resource.
     */
    public function indexAdmin(Request $request)
    {
        $search = $request->input('search', '');
        $status = $request->input('status', '');

        $query = Laboratory::query();

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereRaw('LOWER(description) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereRaw('LOWER(location) LIKE ?', ['%' . strtolower($search) . '%']);
            });
        }

        // Apply status filter
        if ($status) {
            $query->where('status', $status);
        }

        $laboratories = $query->get()->map(function ($laboratory) {
            return [
                'laboratory_id' => $laboratory->laboratory_id,
                'name' => $laboratory->name,
                'description' => $laboratory->description,
                'location' => $laboratory->location,
                'status' => $laboratory->status ?? 'active',
            ];
        });

        return Inertia::render('Auth/Admin/Laboratories', [
            'laboratories' => $laboratories,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'required|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        Laboratory::create($validated);

        return back()->with('success', 'Laboratory added successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $laboratory = Laboratory::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'required|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        $laboratory->update($validated);

        return back()->with('success', 'Laboratory updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $laboratory = Laboratory::findOrFail($id);
        $laboratory->delete();

        return back()->with('success', 'Laboratory deleted successfully.');
    }
}
