<?php

namespace App\Http\Controllers;

use App\Models\Instructor;
use App\Models\User;
use App\Models\Course;
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
        $course = $request->input('course', '');
        $status = $request->input('status', '');

        $query = Instructor::with('user', 'course');

        // Apply search filter
        if ($search) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereRaw('LOWER(email) LIKE ?', ['%' . strtolower($search) . '%']);
            })->orWhereRaw('LOWER(instructor_number) LIKE ?', ['%' . strtolower($search) . '%']);
        }

        // Apply course filter
        if ($course) {
            $courseRecord = Course::where('course_code', $course)->first();
            if ($courseRecord) {
                $query->where('course_id', $courseRecord->course_id);
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
                'course_id' => $instructor->course_id,
                'course_code' => $instructor->course?->course_code ?? '',
                'rfid_tag' => $user->rfid_tag ?? '',
                'status' => $instructor->status ?? 'active',
            ];
        });

        // Get available courses for the form
        $courses = Course::where('status', 'active')->get()->map(function ($course) {
            return [
                'course_id' => $course->course_id,
                'course_code' => $course->course_code,
                'course_name' => $course->course_name,
            ];
        });

        return Inertia::render('Auth/Admin/Instructors', [
            'instructors' => $instructors,
            'courses' => $courses,
            'filters' => [
                'search' => $search,
                'course' => $course,
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
            'course_id' => 'required|exists:courses,course_id',
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
            'role' => 'instructor',
        ]);

        // Create instructor record
        Instructor::create([
            'user_id' => $user->user_id,
            'course_id' => $validated['course_id'],
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
            'course_id' => 'required|exists:courses,course_id',
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
            'course_id' => $validated['course_id'],
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
