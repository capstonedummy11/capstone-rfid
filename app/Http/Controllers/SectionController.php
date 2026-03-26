<?php

namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\Course;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SectionController
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

        $query = Section::query();

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(section_name) LIKE ?', ['%' . strtolower($search) . '%']);
            });
        }

        // Apply course filter
        if ($course) {
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

        $sections = $query->get()->map(function ($section) {
            return [
                'section_id' => $section->section_id,
                'section_name' => $section->section_name,
                'course_id' => $section->course_id,
                'course_code' => $this->getCourseCode($section->course_id),
                'year_level' => $section->year_level,
                'semester' => $section->semester,
                'school_year' => $section->school_year,
                'status' => $section->status ?? 'active',
            ];
        });

        return Inertia::render('Auth/Admin/Sections', [
            'sections' => $sections,
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
            'section_name' => 'required|unique:sections',
            'course_id' => 'required|exists:courses,course_id',
            'year_level' => 'required|integer|between:1,4',
            'semester' => 'required|integer|between:1,2',
            'school_year' => 'required|string|max:9',
            'status' => 'required|in:active,inactive',
        ]);

        Section::create($validated);

        return back()->with('success', 'Section added successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Section $section)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Section $section)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $section = Section::findOrFail($id);

        $validated = $request->validate([
            'section_name' => 'required|unique:sections,section_name,' . $id . ',section_id',
            'course_id' => 'required|exists:courses,course_id',
            'year_level' => 'required|integer|between:1,4',
            'semester' => 'required|integer|between:1,2',
            'school_year' => 'required|string|max:9',
            'status' => 'required|in:active,inactive',
        ]);

        $section->update($validated);

        return back()->with('success', 'Section updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $section = Section::findOrFail($id);
        $section->delete();

        return back()->with('success', 'Section deleted successfully.');
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
