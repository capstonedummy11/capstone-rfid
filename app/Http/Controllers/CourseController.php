<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CourseController
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

        $query = Course::query();

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(course_code) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereRaw('LOWER(course_name) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereRaw('LOWER(department) LIKE ?', ['%' . strtolower($search) . '%']);
            });
        }

        // Apply status filter
        if ($status) {
            $query->where('status', $status);
        }

        $courses = $query->get()->map(function ($course) {
            return [
                'course_id' => $course->course_id,
                'course_code' => $course->course_code,
                'course_name' => $course->course_name,
                'department' => $course->department,
                'status' => $course->status ?? 'active',
            ];
        });

        return Inertia::render('Auth/Admin/Courses', [
            'courses' => $courses,
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
            'course_code' => 'required|unique:courses',
            'course_name' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        Course::create($validated);

        return back()->with('success', 'Course added successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Course $course)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Course $course)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $course = Course::findOrFail($id);

        $validated = $request->validate([
            'course_code' => 'required|unique:courses,course_code,' . $id . ',course_id',
            'course_name' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        $course->update($validated);

        return back()->with('success', 'Course updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $course = Course::findOrFail($id);
        $course->delete();

        return back()->with('success', 'Course deleted successfully.');
    }
}
