<?php

namespace App\Http\Controllers;

use App\Models\Instructor;
use App\Models\Strand;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InstructorsController
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
        $strand = $request->input('strand', '');
        $status = $request->input('status', '');

        $query = Instructor::with('user', 'strand');

        // Apply search filter
        if ($search) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereRaw('LOWER(email) LIKE ?', ['%' . strtolower($search) . '%']);
            })->orWhereRaw('LOWER(instructor_number) LIKE ?', ['%' . strtolower($search) . '%']);
        }

        // Apply strand filter
        if ($strand) {
            $strandRecord = Strand::where('strand_code', $strand)->first();
            if ($strandRecord) {
                $query->where('strand_id', $strandRecord->strand_id);
            }
        }

        // Apply status filter
        if ($status) {
            $query->where('status', $status);
        }

        $instructors = $query->get()->map(function ($instructor) {
            $user = $instructor->user;
            return [
                'instructor_id' => $instructor->instructor_id,
                'user_id' => $instructor->user_id,
                'instructor_number' => $instructor->instructor_number,
                'first_name' => $this->getFirstName($user->name),
                'middle_name' => $user->middle_name ?? '',
                'last_name' => $user->last_name ?? '',
                'email' => $user->email,
                'phone' => $user->phone ?? '',
                'gender' => $user->gender ?? '',
                'strand_id' => $instructor->strand_id,
                'strand_code' => $instructor->strand?->strand_code ?? '',
                'rfid_tag' => $user->rfid_tag ?? '',
                'status' => $instructor->status ?? 'active',
            ];
        });

        // Get available strands for the form
        $strands = Strand::where('status', 'active')->get()->map(function ($strand) {
            return [
                'strand_id' => $strand->strand_id,
                'strand_code' => $strand->strand_code,
                'strand_name' => $strand->strand_name,
            ];
        });

        return Inertia::render('Auth/Admin/Instructors', [
            'instructors' => $instructors,
            'strands' => $strands,
            'filters' => [
                'search' => $search,
                'strand' => $strand,
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
            'instructor_number' => 'required|unique:instructors',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|in:male,female',
            'strand_id' => 'required|exists:strands,strand_id',
            'rfid_tag' => 'nullable|string|unique:users,rfid_tag',
            'status' => 'required|in:active,inactive,on_leave',
        ]);

        // Create user record
        $user = User::create([
            'name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'rfid_tag' => $validated['rfid_tag'] ?? null,
            'password' => bcrypt('password'), // Default password
            'must_change_password' => true,
            'role' => 'instructor',
        ]);

        // Create instructor record
        Instructor::create([
            'user_id' => $user->user_id,
            'strand_id' => $validated['strand_id'],
            'instructor_number' => $validated['instructor_number'],
            'status' => $validated['status'],
        ]);

        return back()->with('success', 'Instructor added successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Instructor $instructor)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Instructor $instructor)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $instructor = Instructor::with('user')->findOrFail($id);

        $validated = $request->validate([
            'instructor_number' => 'required|unique:instructors,instructor_number,' . $id . ',instructor_id',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $instructor->user_id . ',user_id',
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|in:male,female',
            'strand_id' => 'required|exists:strands,strand_id',
            'rfid_tag' => 'nullable|string|unique:users,rfid_tag,' . $instructor->user_id . ',user_id',
            'status' => 'required|in:active,inactive,on_leave',
        ]);

        // Update user record
        $instructor->user->update([
            'name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'rfid_tag' => $validated['rfid_tag'] ?? null,
        ]);

        // Update instructor record
        $instructor->update([
            'strand_id' => $validated['strand_id'],
            'instructor_number' => $validated['instructor_number'],
            'status' => $validated['status'],
        ]);

        return back()->with('success', 'Instructor updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $instructor = Instructor::with('user')->findOrFail($id);
        
        // Delete user record (which will cascade delete instructor)
        $instructor->user->delete();

        return back()->with('success', 'Instructor deleted successfully.');
    }

    /**
     * Extract first name from full name
     */
    private function getFirstName($fullName)
    {
        return explode(' ', trim($fullName))[0] ?? $fullName;
    }
}
