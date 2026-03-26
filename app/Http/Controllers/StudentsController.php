<?php

namespace App\Http\Controllers;

use App\Models\Students;
use App\Models\Course;
use App\Models\Section;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StudentsController
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
        $year = $request->input('year', '');
        $status = $request->input('status', '');

        $query = Students::query();

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereRaw('LOWER(student_number) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereRaw('LOWER(email) LIKE ?', ['%' . strtolower($search) . '%']);
            });
        }

        // Apply course filter
        if ($course) {
            // Assuming there's a relationship to Course model
            $courseRecord = Course::where('course_code', $course)->first();
            if ($courseRecord) {
                $query->where('course_id', $courseRecord->course_id);
            }
        }

        // Apply year filter
        if ($year) {
            $query->where('year_level', $year);
        }

        // Apply status filter
        if ($status) {
            $query->where('status', $status);
        }

        $students = $query->get()->map(function ($student) {
            return [
                'student_id' => $student->student_id,
                'student_number' => $student->student_number,
                'first_name' => $student->first_name,
                'middle_name' => $student->middle_name,
                'last_name' => $student->last_name,
                'email' => $student->email,
                'phone' => $student->phone,
                'gender' => $student->gender,
                'course_id' => $student->course_id,
                'course_code' => $this->getCourseCode($student->course_id),
                'section_id' => $student->section_id,
                'year_level' => $student->year_level,
                'semester' => $student->semester,
                'school_year' => $student->school_year,
                'rfid_tag' => $student->rfid_tag,
                'status' => $student->status ?? 'active',
            ];
        });

        return Inertia::render('Auth/Admin/Students', [
            'students' => $students,
            'filters' => [
                'search' => $search,
                'course' => $course,
                'year' => $year,
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
            'student_number' => 'required|unique:students',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:students',
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|in:male,female',
            'course_id' => 'required|exists:courses,course_id',
            'section_id' => 'required|exists:sections,section_id',
            'year_level' => 'required|integer|between:1,4',
            'semester' => 'required|integer|between:1,2',
            'school_year' => 'required|string|max:9',
            'rfid_tag' => 'nullable|string|unique:students',
            'status' => 'required|in:active,inactive,graduated',
        ]);

        Students::create($validated);

        return back()->with('success', 'Student added successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Students $students)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Students $students)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $student = Students::findOrFail($id);

        $validated = $request->validate([
            'student_number' => 'required|unique:students,student_number,' . $id . ',student_id',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:students,email,' . $id . ',student_id',
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|in:male,female',
            'course_id' => 'required|exists:courses,course_id',
            'section_id' => 'required|exists:sections,section_id',
            'year_level' => 'required|integer|between:1,4',
            'semester' => 'required|integer|between:1,2',
            'school_year' => 'required|string|max:9',
            'rfid_tag' => 'nullable|string|unique:students,rfid_tag,' . $id . ',student_id',
            'status' => 'required|in:active,inactive,graduated',
        ]);

        $student->update($validated);

        return back()->with('success', 'Student updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $student = Students::findOrFail($id);
        $student->delete();

        return back()->with('success', 'Student deleted successfully.');
    }

    /**
     * Get course code from course_id
     */
    private function getCourseCode($courseId)
    {
        if (!$courseId) return '';
        $course = Course::find($courseId);
        return $course ? ($course->course_code ?? '') : '';
    }
}

