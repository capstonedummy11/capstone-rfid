<?php
// FEATURE:academic-scheduling - konektadong model, service, route, o UI para sa feature na ito.

namespace App\Http\Controllers\Admin\Strands;

use App\Models\Strand;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class StrandController
{
    // @function index: Wala pang implementasyon ang legacy index placeholder.
    // @useIn index: TODO(verify): walang direct caller na nakita sa static search
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    // @function indexAdmin: Ibinabalik ang Auth/Admin/Strands page at data para sa request.
    // @useIn indexAdmin: routes/web.php:401 (strands.index)
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
                $q->whereRaw('LOWER(strand_code) LIKE ?', ['%'.strtolower($search).'%'])
                    ->orWhereRaw('LOWER(strand_name) LIKE ?', ['%'.strtolower($search).'%'])
                    ->orWhereRaw('LOWER(department) LIKE ?', ['%'.strtolower($search).'%']);
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

        return Inertia::render('Admin/Strands/StrandsPage', [
            'title' => 'Strands',
            'strands' => $strands,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    // @function create: Inihahanda ang create form o page.
    // @useIn create: StrandController::store (app/Http/Controllers/Admin/Strands/StrandController.php)
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    // @function store: Pinoproseso ang bagong Strand record.
    // @useIn store: routes/web.php:402 (strands.store)
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'strand_code' => ['required', Rule::unique('strands', 'strand_code')->whereNull('deleted_at')],
            'strand_name' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        Strand::create($validated);

        return back()->with('success', 'Strand added successfully.');
    }

    // @function show: Ibinabalik ang detalye ng napiling record.
    // @useIn show: TODO(verify): walang direct caller na nakita sa static search
    /**
     * Display the specified resource.
     */
    public function show(Strand $strand)
    {
        //
    }

    // @function edit: Inihahanda ang edit form o page.
    // @useIn edit: TODO(verify): walang direct caller na nakita sa static search
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Strand $strand)
    {
        //
    }

    // @function update: Pinoproseso ang pagbabago sa Strand record.
    // @useIn update: routes/web.php:403 (strands.update)
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $strand = Strand::findOrFail($id);

        $validated = $request->validate([
            'strand_code' => ['required', Rule::unique('strands', 'strand_code')->whereNull('deleted_at')->ignore($id, 'strand_id')],
            'strand_name' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        $strand->update($validated);

        return back()->with('success', 'Strand updated successfully.');
    }

    // @function destroy: Pinoproseso ang pagtanggal ng Strand record.
    // @useIn destroy: routes/web.php:404 (strands.destroy)
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
