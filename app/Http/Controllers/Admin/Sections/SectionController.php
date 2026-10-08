<?php
// FEATURE:academic-scheduling - konektadong model, service, route, o UI para sa feature na ito.

namespace App\Http\Controllers\Admin\Sections;

use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\Section;
use App\Models\Strand;
use App\Services\StudentEnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SectionController
{
    // @function __construct: Tinatanggap ang dependencies ng Section sa pagbuo ng object.
    // @useIn __construct: Laravel dependency injection kapag ginagamit ang SectionController
    public function __construct(private readonly StudentEnrollmentService $studentEnrollmentService) {}

    // @function index: Wala pang implementasyon ang legacy index placeholder.
    // @useIn index: TODO(verify): walang direct caller na nakita sa static search
    public function index()
    {
        //
    }

    // @function indexAdmin: Ibinabalik ang Auth/Admin/Sections page at data para sa request.
    // @useIn indexAdmin: routes/web.php:321 (sections.index)
    public function indexAdmin(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'strand' => trim((string) $request->input('strand', '')),
            'year' => trim((string) $request->input('year', '')),
            'status' => trim((string) $request->input('status', '')),
            'academic_year' => trim((string) $request->input('academic_year', '')),
            'semester' => trim((string) $request->input('semester', '')),
        ];
        $defaultAcademicYear = AcademicYear::currentOrLatest();
        if ($filters['academic_year'] === '' && $defaultAcademicYear) {
            $filters['academic_year'] = (string) $defaultAcademicYear->academic_year_id;
        }

        $query = Section::query()->with(['strand', 'academicYear']);

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

        if (! in_array($filters['academic_year'], ['', 'all'], true)) {
            $query->where('academic_year_id', $filters['academic_year']);
        }

        if ($filters['semester'] !== '') {
            $query->where('semester', $filters['semester']);
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
                    'academic_year_id' => $section->academic_year_id,
                    'academic_year_status' => $section->academicYear?->status,
                    'is_writable' => $section->academicYear?->isWritable() ?? true,
                    'status' => $section->status ?? 'active',
                ];
            })
            ->values();

        return Inertia::render('Admin/Sections/SectionsPage', [
            'title' => 'Sections',
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
            'academicYearOptions' => AcademicYear::query()
                ->orderByDesc('starts_on')
                ->get(['academic_year_id', 'name', 'status', 'active_semester'])
                ->values(),
            'activeAcademicYearId' => $defaultAcademicYear?->academic_year_id,
        ]);
    }

    // @function create: Inihahanda ang create form o page.
    // @useIn create: SectionController::store (app/Http/Controllers/Admin/Sections/SectionController.php)
    public function create()
    {
        //
    }

    // @function store: Pinoproseso ang bagong Section record.
    // @useIn store: routes/web.php:322 (sections.store)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'section_name' => ['required', 'string', 'max:255'],
            'strand_id' => 'required|exists:strands,strand_id',
            'year_level' => 'required|integer|in:11,12',
            'semester' => 'required|string|max:50',
            'school_year' => 'required|string|max:20',
            'status' => 'required|in:active,inactive',
        ]);

        $academicYear = $this->studentEnrollmentService->yearForLabel($validated['school_year']);
        if (! $academicYear->isWritable()) {
            return back()->withErrors(['school_year' => 'Sections cannot be added to a closed or archived academic year.']);
        }

        $request->validate([
            'section_name' => Rule::unique('sections', 'section_name')->where(fn ($query) => $query
                ->where('academic_year_id', $academicYear->academic_year_id)
                ->where('semester', $validated['semester'])
                ->whereNull('deleted_at')),
        ]);

        $section = Section::create($validated + ['academic_year_id' => $academicYear->academic_year_id]);
        $this->logActivity('create', 'sections', 'Created section '.$section->section_name);

        return back()->with('success', 'Section added successfully.');
    }

    // @function show: Ibinabalik ang detalye ng napiling record.
    // @useIn show: TODO(verify): walang direct caller na nakita sa static search
    public function show(Section $section)
    {
        //
    }

    // @function edit: Inihahanda ang edit form o page.
    // @useIn edit: TODO(verify): walang direct caller na nakita sa static search
    public function edit(Section $section)
    {
        //
    }

    // @function update: Pinoproseso ang pagbabago sa Section record.
    // @useIn update: routes/web.php:323 (sections.update)
    public function update(Request $request, $id)
    {
        $section = Section::findOrFail($id);

        if ($section->academicYear && ! $section->academicYear->isWritable()) {
            return back()->withErrors(['section' => 'A section from a closed or archived academic year cannot be edited.']);
        }

        $validated = $request->validate([
            'section_name' => ['required', 'string', 'max:255'],
            'strand_id' => 'required|exists:strands,strand_id',
            'year_level' => 'required|integer|in:11,12',
            'semester' => 'required|string|max:50',
            'school_year' => 'required|string|max:20',
            'status' => 'required|in:active,inactive',
        ]);

        $academicYear = $this->studentEnrollmentService->yearForLabel($validated['school_year']);
        if (! $academicYear->isWritable()) {
            return back()->withErrors(['school_year' => 'A section cannot be moved into a closed or archived academic year.']);
        }

        $request->validate([
            'section_name' => Rule::unique('sections', 'section_name')
                ->ignore($section->section_id, 'section_id')
                ->where(fn ($query) => $query
                    ->where('academic_year_id', $academicYear->academic_year_id)
                    ->where('semester', $validated['semester'])
                    ->whereNull('deleted_at')),
        ]);

        $section->update($validated + ['academic_year_id' => $academicYear->academic_year_id]);
        $this->logActivity('update', 'sections', 'Updated section '.$section->section_name);

        return back()->with('success', 'Section updated successfully.');
    }

    // @function destroy: Pinoproseso ang pagtanggal ng Section record.
    // @useIn destroy: routes/web.php:324 (sections.destroy)
    public function destroy($id)
    {
        $section = Section::findOrFail($id);
        if ($section->academicYear && ! $section->academicYear->isWritable()) {
            return back()->withErrors(['section' => 'A section from a closed or archived academic year cannot be deleted.']);
        }
        if ($section->students()->exists() || $section->subjects()->exists() || $section->schedules()->exists() || $section->enrollments()->exists()) {
            return back()->withErrors(['section' => 'This section has student, subject, schedule, or enrollment history and cannot be deleted. Deactivate it instead.']);
        }
        $sectionName = $section->section_name;
        $section->delete();

        $this->logActivity('delete', 'sections', 'Deleted section '.$sectionName);

        return back()->with('success', 'Section deleted successfully.');
    }

    // @function logActivity: Nilolog ang activity sa Section flow.
    // @useIn logActivity: SectionController::store (app/Http/Controllers/Admin/Sections/SectionController.php)
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
