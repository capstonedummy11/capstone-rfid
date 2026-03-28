<?php

namespace App\Http\Controllers;

use App\Models\Strand;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StrandController
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

        $query = Strand::query();

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(strand_code) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereRaw('LOWER(strand_name) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereRaw('LOWER(department) LIKE ?', ['%' . strtolower($search) . '%']);
            });
        }

        // Apply status filter
        if ($status) {
            $query->where('status', $status);
        }

        $strands = $query->get()->map(function ($strand) {
            return [
                'strand_id' => $strand->strand_id,
                'strand_code' => $strand->strand_code,
                'strand_name' => $strand->strand_name,
                'department' => $strand->department,
                'status' => $strand->status ?? 'active',
            ];
        });

        return Inertia::render('Auth/Admin/Strands', [
            'strands' => $strands,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'strand_code' => 'required|unique:strands,strand_code',
            'strand_name' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        Strand::create($validated);

        return back()->with('success', 'Strand added successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Strand $strand)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Strand $strand)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $strand = Strand::findOrFail($id);

        $validated = $request->validate([
            'strand_code' => 'required|unique:strands,strand_code,' . $id . ',strand_id',
            'strand_name' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        $strand->update($validated);

        return back()->with('success', 'Strand updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $strand = Strand::findOrFail($id);
        $strand->delete();

        return back()->with('success', 'Strand deleted successfully.');
    }
}
