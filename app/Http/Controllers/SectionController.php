<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Section;
use App\Models\Strand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SectionController
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

        $query = Section::query()->with(['strand']);

        if ($filters['search'] !== '') {
            $term = strtolower($filters['search']);
            $query->whereRaw('LOWER(section_name) LIKE ?', ["%{$term}%"]);
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

        $sections = $query
            ->orderBy('section_name')
            ->get()
            ->map(function (Section $section) {
                return [
                    'section_id' => $section->section_id,
                    'section_name' => $section->section_name,
                    'strand_id' => $section->strand_id,
                    'strand_code' => $section->strand?->strand_code,
                    'year_level' => $section->year_level,
                    'semester' => $section->semester,
                    'school_year' => $section->school_year,
                    'status' => $section->status ?? 'active',
                ];
            })
            ->values();

        return Inertia::render('Auth/Admin/Sections', [
            'sections' => $sections,
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
        ]);
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'section_name' => 'required|string|max:255|unique:sections,section_name',
            'strand_id' => 'required|exists:strands,strand_id',
            'year_level' => 'required|integer|in:11,12',
            'semester' => 'required|string|max:50',
            'school_year' => 'required|string|max:20',
            'status' => 'required|in:active,inactive',
        ]);

        $section = Section::create($validated);
        $this->logActivity('create', 'sections', 'Created section ' . $section->section_name);

        return back()->with('success', 'Section added successfully.');
    }

    public function show(Section $section)
    {
        //
    }

    public function edit(Section $section)
    {
        //
    }

    public function update(Request $request, $id)
    {
        $section = Section::findOrFail($id);

        $validated = $request->validate([
            'section_name' => 'required|string|max:255|unique:sections,section_name,' . $id . ',section_id',
            'strand_id' => 'required|exists:strands,strand_id',
            'year_level' => 'required|integer|in:11,12',
            'semester' => 'required|string|max:50',
            'school_year' => 'required|string|max:20',
            'status' => 'required|in:active,inactive',
        ]);

        $section->update($validated);
        $this->logActivity('update', 'sections', 'Updated section ' . $section->section_name);

        return back()->with('success', 'Section updated successfully.');
    }

    public function destroy($id)
    {
        $section = Section::findOrFail($id);
        $sectionName = $section->section_name;
        $section->delete();

        $this->logActivity('delete', 'sections', 'Deleted section ' . $sectionName);

        return back()->with('success', 'Section deleted successfully.');
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
