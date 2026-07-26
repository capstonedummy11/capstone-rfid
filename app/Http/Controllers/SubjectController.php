<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SubjectController
{
    public function indexAdmin(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'semester' => trim((string) $request->input('semester', '')),
        ];

        $query = Subject::query()->with(['section', 'user']);

        if ($filters['search'] !== '') {
            $term = strtolower($filters['search']);
            $query->where(function ($subjectQuery) use ($term) {
                $subjectQuery
                    ->whereRaw('LOWER(subject_name) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(subject_code) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(department) LIKE ?', ["%{$term}%"]);
            });
        }

        if ($filters['semester'] !== '') {
            $query->where('semester', $filters['semester']);
        }

        return Inertia::render('Auth/Admin/Subjects', [
            'subjects' => $query->orderBy('subject_code')->get()->map(fn (Subject $subject) => [
                'subject_id' => $subject->subject_id,
                'section_id' => $subject->section_id,
                'section_name' => $subject->section?->section_name,
                'user_id' => $subject->user_id,
                'user_name' => $subject->user?->name,
                'subject_name' => $subject->subject_name,
                'subject_code' => $subject->subject_code,
                'subject_description' => $subject->subject_description,
                'department' => $subject->department,
                'unit' => $subject->unit,
                'semester' => $subject->semester,
            ])->values(),
            'filters' => $filters,
            'sectionOptions' => Section::query()
                ->orderBy('section_name')
                ->get(['section_id', 'section_name', 'year_level', 'school_year'])
                ->map(fn (Section $section) => [
                    'section_id' => $section->section_id,
                    'section_name' => $section->section_name,
                    'year_level' => $section->year_level,
                    'school_year' => $section->school_year,
                    'label' => trim(implode(' - ', array_filter([
                        $section->section_name,
                        $section->year_level ? 'Grade '.$section->year_level : null,
                        $section->school_year,
                    ]))),
                ])
                ->values(),
            'instructorOptions' => User::query()->orderBy('name')->get(['user_id', 'name', 'role'])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'section_id' => 'nullable|exists:sections,section_id',
            'user_id' => 'nullable|exists:users,user_id',
            'subject_name' => 'required|string|max:255',
            'subject_code' => 'required|string|max:255|unique:subjects,subject_code',
            'subject_description' => 'nullable|string',
            'department' => 'nullable|string|max:255',
            'unit' => 'required|integer|min:0',
            'semester' => 'nullable|string|max:255',
        ]);

        $subject = Subject::create($validated);
        $this->log('create', 'subjects', 'Created subject ' . $subject->subject_code);

        return back()->with('success', 'Subject added successfully.');
    }

    public function update(Request $request, int $id)
    {
        $subject = Subject::findOrFail($id);

        $validated = $request->validate([
            'section_id' => 'nullable|exists:sections,section_id',
            'user_id' => 'nullable|exists:users,user_id',
            'subject_name' => 'required|string|max:255',
            'subject_code' => 'required|string|max:255|unique:subjects,subject_code,' . $id . ',subject_id',
            'subject_description' => 'nullable|string',
            'department' => 'nullable|string|max:255',
            'unit' => 'required|integer|min:0',
            'semester' => 'nullable|string|max:255',
        ]);

        $subject->update($validated);
        $this->log('update', 'subjects', 'Updated subject ' . $subject->subject_code);

        return back()->with('success', 'Subject updated successfully.');
    }

    public function destroy(int $id)
    {
        $subject = Subject::findOrFail($id);
        $code = $subject->subject_code;
        $subject->delete();
        $this->log('delete', 'subjects', 'Deleted subject ' . $code);

        return back()->with('success', 'Subject deleted successfully.');
    }

    private function log(string $action, string $tableName, string $description): void
    {
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'table_name' => $tableName,
            'description' => $description,
        ]);
    }
}
