<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Section;
use App\Models\Strand;
use App\Models\Students;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class StudentsController
{
    public function index()
    {
        //
    }

    public function indexAdmin(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'strand' => trim((string) $request->input('strand', '')),
            'year' => trim((string) $request->input('year', '')),
            'status' => trim((string) $request->input('status', '')),
        ];

        $query = Students::query()->with(['section', 'strand']);

        if ($filters['search'] !== '') {
            $term = strtolower($filters['search']);
            $query->where(function ($studentQuery) use ($term) {
                $studentQuery
                    ->whereRaw('LOWER(first_name) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(student_number) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(rfid_tag) LIKE ?', ["%{$term}%"]);
            });
        }

        if ($filters['strand'] !== '') {
            $query->where('strand_id', $filters['strand']);
        }

        if ($filters['year'] !== '') {
            $query->where('year_level', $filters['year']);
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        $students = $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function (Students $student) {
                return [
                    'student_id' => $student->student_id,
                    'student_number' => $student->student_number,
                    'first_name' => $student->first_name,
                    'middle_name' => $student->middle_name,
                    'last_name' => $student->last_name,
                    'email' => $student->email,
                    'phone' => $student->phone,
                    'gender' => $student->gender,
                    'strand_id' => $student->strand_id,
                    'strand_code' => $student->strand?->strand_code,
                    'section_id' => $student->section_id,
                    'section_name' => $student->section?->section_name,
                    'year_level' => $student->year_level,
                    'semester' => $student->semester,
                    'school_year' => $student->school_year,
                    'rfid_tag' => $student->rfid_tag,
                    'status' => $student->status ?? 'active',
                ];
            })
            ->values();

        return Inertia::render('Auth/Admin/Students', [
            'students' => $students,
            'filters' => $filters,
            'strandOptions' => Strand::query()
                ->orderBy('strand_code')
                ->get(['strand_id', 'strand_code', 'strand_name'])
                ->map(fn (Strand $strand) => [
                    'strand_id' => $strand->strand_id,
                    'strand_code' => $strand->strand_code,
                    'strand_name' => $strand->strand_name,
                ])
                ->values(),
            'sectionOptions' => Section::query()
                ->with(['strand'])
                ->orderBy('section_name')
                ->get()
                ->map(fn (Section $section) => [
                    'section_id' => $section->section_id,
                    'section_name' => $section->section_name,
                    'strand_id' => $section->strand_id,
                    'label' => trim(implode(' - ', array_filter([
                        $section->section_name,
                        $section->strand?->strand_code,
                    ]))),
                ])
                ->values(),
        ]);
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_number' => 'required|string|max:255|unique:students,student_number',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:students,email',
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|in:male,female',
            'strand_id' => 'required|exists:strands,strand_id',
            'section_id' => 'required|exists:sections,section_id',
            'year_level' => 'required|integer|in:11,12',
            'semester' => 'required|string|max:50',
            'school_year' => 'required|string|max:20',
            'rfid_tag' => 'nullable|string|max:255|unique:students,rfid_tag',
            'status' => 'required|in:active,inactive,graduated,dropped',
        ]);

        $student = Students::create($validated);
        $this->logActivity('create', 'students', 'Created student ' . $student->student_number);

        return back()->with('success', 'Student added successfully.');
    }

    public function show(Students $students)
    {
        //
    }

    public function edit(Students $students)
    {
        //
    }

    public function update(Request $request, $id)
    {
        $student = Students::findOrFail($id);

        $validated = $request->validate([
            'student_number' => 'required|string|max:255|unique:students,student_number,' . $id . ',student_id',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:students,email,' . $id . ',student_id',
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|in:male,female',
            'strand_id' => 'required|exists:strands,strand_id',
            'section_id' => 'required|exists:sections,section_id',
            'year_level' => 'required|integer|in:11,12',
            'semester' => 'required|string|max:50',
            'school_year' => 'required|string|max:20',
            'rfid_tag' => 'nullable|string|max:255|unique:students,rfid_tag,' . $id . ',student_id',
            'status' => 'required|in:active,inactive,graduated,dropped',
        ]);

        $student->update($validated);
        $this->logActivity('update', 'students', 'Updated student ' . $student->student_number);

        return back()->with('success', 'Student updated successfully.');
    }

    public function destroy($id)
    {
        $student = Students::findOrFail($id);
        $studentNumber = $student->student_number;
        $student->delete();

        $this->logActivity('delete', 'students', 'Deleted student ' . $studentNumber);

        return back()->with('success', 'Student deleted successfully.');
    }

    private function logActivity(string $action, string $tableName, string $description): void
    {
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'table_name' => $tableName,
            'description' => $description,
        ]);
    }
}

