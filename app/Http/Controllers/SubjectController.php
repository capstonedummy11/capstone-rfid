<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AcademicYear;
use App\Models\Instructor;
use App\Models\Section;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SubjectController
{
    public function indexAdmin(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'semester' => trim((string) $request->input('semester', '')),
            'academic_year_id' => $request->input('academic_year_id'),
        ];
        $defaultYearId = AcademicYear::currentOrLatest()?->academic_year_id;
        $yearId = $filters['academic_year_id'] === 'all' ? null : ($request->integer('academic_year_id') ?: $defaultYearId);
        $filters['academic_year_id'] = $filters['academic_year_id'] === 'all' ? 'all' : $yearId;
        $selectedAcademicYear = $yearId ? AcademicYear::find($yearId) : null;
        if ($filters['semester'] === '' && $yearId) {
            $filters['semester'] = $selectedAcademicYear?->active_semester ?: '';
        }

        $query = Subject::query()->with(['section', 'user', 'offerings' => fn ($offerings) => $offerings
            ->when($yearId, fn ($yearQuery) => $yearQuery->where('academic_year_id', $yearId))
            ->when($filters['semester'] !== '', fn ($termQuery) => $termQuery->where('semester', $filters['semester']))
            ->with(['academicYear', 'section', 'instructor.user'])]);

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
            $query->whereHas('offerings', fn ($offering) => $offering->where('semester', $filters['semester'])->when($yearId, fn ($year) => $year->where('academic_year_id', $yearId)));
        }
        if ($yearId) {
            $query->whereHas('offerings', fn ($offering) => $offering->where('academic_year_id', $yearId));
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
                'offerings' => $subject->offerings
                    ->sortByDesc(fn (SubjectOffering $offering) => $offering->academicYear?->starts_on)
                    ->map(fn (SubjectOffering $offering) => [
                        'subject_offering_id' => $offering->subject_offering_id,
                        'academic_year' => $offering->academicYear?->name,
                        'academic_year_status' => $offering->academicYear?->status,
                        'semester' => $offering->semester,
                        'section_id' => $offering->section_id,
                        'section_name' => $offering->section?->section_name,
                        'instructor_id' => $offering->instructor_id,
                        'instructor_name' => $offering->instructor?->user?->name,
                        'status' => $offering->status,
                        'is_writable' => $offering->isWritable(),
                    ])->values(),
                'has_locked_offerings' => $subject->offerings->contains(fn (SubjectOffering $offering) => ! $offering->isWritable()),
            ])->values(),
            'filters' => $filters,
            'sectionOptions' => Section::query()
                ->when($yearId, fn ($query) => $query->where('academic_year_id', $yearId))
                ->when($filters['semester'] !== '', fn ($query) => $query->where('semester', $filters['semester']))
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
            'instructorOptions' => User::query()->where('role', 'instructor')->orderBy('name')->get(['user_id', 'name', 'role'])->values(),
            'activeAcademicYearId' => $defaultYearId,
            'activeAcademicYearSemester' => $selectedAcademicYear?->active_semester,
            'academicYears' => AcademicYear::query()->orderByDesc('starts_on')->get(['academic_year_id', 'name', 'status', 'active_semester']),
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
        $subject = DB::transaction(function () use ($validated) {
            $subject = Subject::create(collect($validated)->except(['section_id', 'user_id', 'semester'])->all());
            $this->syncOfferingFromLegacyFields($subject, $validated);

            return $subject;
        });
        $this->log('create', 'subjects', 'Created subject ' . $subject->subject_code);

        return back()->with('success', 'Subject added successfully.');
    }

    public function update(Request $request, int $id)
    {
        $subject = Subject::findOrFail($id);

        $hasLockedOffering = $subject->offerings()->with('academicYear')->get()
            ->contains(fn (SubjectOffering $offering) => ! $offering->isWritable());

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

        if ($hasLockedOffering && ($validated['subject_code'] !== $subject->subject_code || $validated['subject_name'] !== $subject->subject_name)) {
            return back()->withErrors(['subject' => 'The code and name cannot be changed because this subject has a closed or archived offering. Other catalog details can still be updated.']);
        }

        DB::transaction(function () use ($subject, $validated) {
            $subject->update(collect($validated)->except(['section_id', 'user_id', 'semester'])->all());
            $this->syncOfferingFromLegacyFields($subject, $validated);
        });
        $this->log('update', 'subjects', 'Updated subject ' . $subject->subject_code);

        return back()->with('success', 'Subject updated successfully.');
    }

    public function destroy(int $id)
    {
        $subject = Subject::findOrFail($id);
        if ($subject->offerings()->exists() || $subject->schedules()->exists()) {
            return back()->withErrors(['subject' => 'This subject has offering or schedule history and cannot be deleted.']);
        }
        $code = $subject->subject_code;
        $subject->delete();
        $this->log('delete', 'subjects', 'Deleted subject ' . $code);

        return back()->with('success', 'Subject deleted successfully.');
    }

    public function storeOffering(Request $request, Subject $subject)
    {
        $validated = $request->validate([
            'section_id' => ['required', 'exists:sections,section_id'],
            'user_id' => ['nullable', 'exists:users,user_id'],
            'semester' => ['required', 'string', 'max:50'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $section = Section::query()->with('academicYear')->findOrFail($validated['section_id']);
        if (! $section->academicYear || ! $section->academicYear->isWritable()) {
            return back()->withErrors(['section_id' => 'Subject offerings can only be added to a draft or active academic year section.']);
        }

        $currentAcademicYear = AcademicYear::currentOrLatest();
        if (! $currentAcademicYear || (int) $section->academic_year_id !== (int) $currentAcademicYear->academic_year_id) {
            return back()->withErrors(['section_id' => 'Subject offerings can only be added to the current academic year.']);
        }
        if ($currentAcademicYear->active_semester && ($section->semester !== $currentAcademicYear->active_semester || $validated['semester'] !== $currentAcademicYear->active_semester)) {
            return back()->withErrors(['semester' => "Subject offerings can only be added for {$currentAcademicYear->active_semester}."]);
        }

        $instructorId = $this->instructorIdForUser($validated['user_id'] ?? null);
        if (SubjectOffering::query()
            ->where('academic_year_id', $section->academic_year_id)
            ->where('subject_id', $subject->subject_id)
            ->where('section_id', $section->section_id)
            ->where('semester', $validated['semester'])
            ->exists()) {
            return back()->withErrors(['section_id' => 'This subject is already offered to that section in the selected academic year and semester.']);
        }
        SubjectOffering::query()->create([
            'academic_year_id' => $section->academic_year_id,
            'subject_id' => $subject->subject_id,
            'section_id' => $section->section_id,
            'instructor_id' => $instructorId,
            'semester' => $validated['semester'],
            'status' => $validated['status'] ?? 'active',
        ]);

        $this->log('create', 'subject_offerings', "Added {$subject->subject_code} offering for {$section->section_name} ({$section->school_year}).");

        return back()->with('success', 'Subject offering added.');
    }

    public function destroyOffering(SubjectOffering $subjectOffering)
    {
        $subjectOffering->loadMissing(['academicYear', 'subject']);
        if (! $subjectOffering->isWritable()) {
            return back()->withErrors(['offering' => 'A closed or archived subject offering cannot be deleted.']);
        }

        $hasSchedule = DB::table('schedules')
            ->where('section_id', $subjectOffering->section_id)
            ->where('subject_code', $subjectOffering->subject?->subject_code)
            ->exists();
        if ($hasSchedule) {
            return back()->withErrors(['offering' => 'This offering is used by a schedule and cannot be deleted.']);
        }

        $label = $subjectOffering->subject?->subject_code;
        $subjectOffering->delete();
        $this->log('delete', 'subject_offerings', "Deleted {$label} subject offering.");

        return back()->with('success', 'Subject offering deleted.');
    }

    public function removeOfferingInstructor(SubjectOffering $subjectOffering)
    {
        $subjectOffering->loadMissing(['academicYear', 'subject']);
        if (! $subjectOffering->isWritable()) {
            return back()->withErrors(['offering' => 'A closed or archived subject offering cannot be changed.']);
        }

        $subjectOffering->update(['instructor_id' => null]);
        $this->log('update', 'subject_offerings', "Removed instructor from {$subjectOffering->subject?->subject_code} offering.");

        return back()->with('success', 'Instructor removed from the subject offering.');
    }

    private function syncOfferingFromLegacyFields(Subject $subject, array $validated): void
    {
        if (empty($validated['section_id'])) {
            return;
        }

        $section = Section::query()->with('academicYear')->findOrFail($validated['section_id']);
        if (! $section->academicYear || ! $section->academicYear->isWritable()) {
            throw ValidationException::withMessages([
                'section_id' => 'A subject offering cannot be created or changed in a closed or archived academic year.',
            ]);
        }

        $currentAcademicYear = AcademicYear::currentOrLatest();
        $offeringSemester = $validated['semester'] ?: $section->semester;
        if ($currentAcademicYear && ((int) $section->academic_year_id !== (int) $currentAcademicYear->academic_year_id || ($currentAcademicYear->active_semester && $offeringSemester !== $currentAcademicYear->active_semester))) {
            throw ValidationException::withMessages([
                'semester' => 'Subject offerings must use the current academic year and its active semester.',
            ]);
        }

        SubjectOffering::query()->updateOrCreate(
            [
                'academic_year_id' => $section->academic_year_id,
                'subject_id' => $subject->subject_id,
                'section_id' => $section->section_id,
                'semester' => $offeringSemester,
            ],
            [
                'instructor_id' => $this->instructorIdForUser($validated['user_id'] ?? null),
                'status' => 'active',
            ],
        );
    }

    private function instructorIdForUser(int|string|null $userId): ?int
    {
        if (! $userId) {
            return null;
        }

        return Instructor::query()->where('user_id', $userId)->value('instructor_id');
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
