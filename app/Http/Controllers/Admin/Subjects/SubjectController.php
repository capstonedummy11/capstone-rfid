<?php

// FEATURE:academic-scheduling - konektadong model, service, route, o UI para sa feature na ito.

namespace App\Http\Controllers\Admin\Subjects;

use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\Instructor;
use App\Models\Section;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SubjectController
{
    // @function indexAdmin: Ibinabalik ang Auth/Admin/Subjects page at data para sa request.
    // @useIn indexAdmin: routes/web.php:325 (subjects.index)
    public function indexAdmin(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'semester' => trim((string) $request->input('semester', '')),
            'academic_year_id' => $request->input('academic_year_id'),
            'view' => $request->input('view') === 'archived' ? 'archived' : 'active',
        ];
        $defaultYearId = AcademicYear::currentOrLatest()?->academic_year_id;
        $yearId = $filters['academic_year_id'] === 'all' || ($filters['view'] === 'archived' && ! $filters['academic_year_id'])
            ? null : ($request->integer('academic_year_id') ?: $defaultYearId);
        $filters['academic_year_id'] = $filters['academic_year_id'] === 'all' || ($filters['view'] === 'archived' && ! $yearId)
            ? 'all' : $yearId;
        $selectedAcademicYear = $yearId ? AcademicYear::find($yearId) : null;
        if ($filters['semester'] === '' && $yearId) {
            $filters['semester'] = $selectedAcademicYear?->active_semester ?: '';
        }
        $offeringAcademicYearIds = $this->writableOfferingAcademicYearIds();

        $query = Subject::query()
            ->when($filters['view'] === 'archived', fn ($subjects) => $subjects->onlyTrashed())
            ->with(['section', 'user', 'offerings' => fn ($offerings) => $offerings
            ->when($yearId, fn ($yearQuery) => $yearQuery->where('academic_year_id', $yearId))
            ->when($filters['semester'] !== '', fn ($termQuery) => $termQuery->where('semester', $filters['semester']))
            ->with(['academicYear', 'section', 'instructor.user', 'schedules' => fn ($schedules) => $schedules
                ->orderBy('weekdays')->orderBy('time_start')])]);

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

        return Inertia::render('Admin/Subjects/SubjectsPage', [
            'title' => 'Subjects',
            'subjects' => $query->orderBy('subject_code')->get()->map(fn (Subject $subject) => [
                'subject_id' => $subject->subject_id,
                'deleted_at' => $subject->deleted_at?->toDateTimeString(),
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
                        'schedules' => $offering->schedules->map(fn ($schedule) => [
                            'scheduled_id' => $schedule->scheduled_id,
                            'weekdays' => $schedule->weekdays,
                            'time_start' => substr((string) $schedule->time_start, 0, 5),
                            'time_end' => substr((string) $schedule->time_end, 0, 5),
                            'room' => $schedule->room,
                        ])->values(),
                    ])->values(),
                'has_locked_offerings' => $subject->offerings->contains(fn (SubjectOffering $offering) => ! $offering->isWritable()),
            ])->values(),
            'filters' => $filters,
            'sectionOptions' => Section::query()
                ->with('academicYear')
                ->whereIn('academic_year_id', $offeringAcademicYearIds->all())
                ->orderBy('section_name')
                ->get(['section_id', 'section_name', 'year_level', 'semester', 'school_year', 'academic_year_id'])
                ->map(fn (Section $section) => [
                    'section_id' => $section->section_id,
                    'academic_year_id' => $section->academic_year_id,
                    'academic_year_status' => $section->academicYear?->status,
                    'section_name' => $section->section_name,
                    'year_level' => $section->year_level,
                    'semester' => $section->semester,
                    'school_year' => $section->school_year,
                    'label' => trim(implode(' - ', array_filter([
                        $section->section_name,
                        $section->year_level ? 'Grade '.$section->year_level : null,
                        $section->school_year,
                        $section->semester,
                    ]))),
                ])
                ->values(),
            'instructorOptions' => User::query()->where('role', 'instructor')
                ->whereHas('instructor', fn ($query) => $query->where('status', 'active'))
                ->orderBy('name')->get(['user_id', 'name', 'role'])->values(),
            'activeAcademicYearId' => $defaultYearId,
            'activeAcademicYearSemester' => $selectedAcademicYear?->active_semester,
            'activeAcademicYearName' => $selectedAcademicYear?->name,
            'academicYears' => AcademicYear::query()->orderByDesc('starts_on')->get(['academic_year_id', 'name', 'status', 'active_semester']),
        ]);
    }

    // @function store: Pinoproseso ang bagong Subject record.
    // @useIn store: routes/web.php:326 (subjects.store)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'section_id' => 'required|exists:sections,section_id',
            'user_id' => 'nullable|exists:users,user_id',
            'subject_name' => 'required|string|max:255',
            'subject_code' => ['required', 'string', 'max:255', Rule::unique('subjects', 'subject_code')->whereNull('deleted_at')],
            'subject_description' => 'nullable|string',
            'department' => 'nullable|string|max:255',
            'unit' => 'required|integer|min:0',
            'semester' => ['required', 'string', 'in:1st Semester,2nd Semester'],
        ]);
        $subject = DB::transaction(function () use ($validated) {
            $subject = Subject::create(collect($validated)->except(['section_id', 'user_id'])->all());
            $this->syncOfferingFromLegacyFields($subject, $validated);

            return $subject;
        });
        $this->log('create', 'subjects', 'Created subject '.$subject->subject_code);

        return back()->with('success', 'Subject added successfully.');
    }

    // @function update: Pinoproseso ang pagbabago sa Subject record.
    // @useIn update: routes/web.php:327 (subjects.update)
    public function update(Request $request, int $id)
    {
        $subject = Subject::findOrFail($id);

        $hasLockedOffering = $subject->offerings()->with('academicYear')->get()
            ->contains(fn (SubjectOffering $offering) => ! $offering->isWritable());

        $validated = $request->validate([
            'section_id' => 'nullable|exists:sections,section_id',
            'user_id' => 'nullable|exists:users,user_id',
            'subject_name' => 'required|string|max:255',
            'subject_code' => ['required', 'string', 'max:255', Rule::unique('subjects', 'subject_code')->whereNull('deleted_at')->ignore($id, 'subject_id')],
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
        $this->log('update', 'subjects', 'Updated subject '.$subject->subject_code);

        return back()->with('success', 'Subject updated successfully.');
    }

    // @function destroy: Pinoproseso ang pagtanggal ng Subject record.
    // @useIn destroy: routes/web.php:328 (subjects.destroy)
    public function destroy(int $id)
    {
        $subject = Subject::findOrFail($id);
        $activeSchedule = DB::table('schedules')
            ->whereIn('subject_offering_id', $subject->offerings()->select('subject_offering_id'))
            ->whereIn('academic_year_id', AcademicYear::query()
                ->whereIn('status', ['draft', 'active'])
                ->select('academic_year_id'))
            ->exists();
        if ($activeSchedule) {
            return back()->withErrors(['subject' => 'Remove current-year schedules before archiving this subject. Historical schedules may remain.']);
        }
        $code = $subject->subject_code;
        DB::transaction(function () use ($subject, $code) {
            $subject->delete();
            $this->log('delete', 'subjects', 'Archived subject '.$code);
        });

        return back()->with('success', 'Subject archived. Its offerings and history remain available in historical records.');
    }

    // @function storeOffering: Sine-save ang offering sa Subject flow.
    // @useIn storeOffering: routes/web.php:329 (subjects.offerings.store)
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

        if (! $this->writableOfferingAcademicYearIds()->contains((int) $section->academic_year_id)) {
            return back()->withErrors(['section_id' => 'Subject offerings can only be added to draft or active academic years.']);
        }
        if ($validated['semester'] !== $section->semester) {
            return back()->withErrors(['semester' => "Subject offerings for this section must use {$section->semester}."]);
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

    // @function destroyOffering: Tinatanggal ang offering sa Subject flow.
    // @useIn destroyOffering: routes/web.php:331 (subjects.offerings.destroy)
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

    public function assignOfferingInstructor(Request $request, SubjectOffering $subjectOffering)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,user_id'],
        ]);

        try {
            $subjectOffering->loadMissing(['academicYear', 'subject']);
            if (! $subjectOffering->isWritable()) {
                return back()->withErrors(['offering' => 'A closed or archived subject offering cannot be changed.']);
            }
            $instructor = Instructor::query()
                ->where('user_id', $validated['user_id'])
                ->where('status', 'active')
                ->whereHas('user', fn ($query) => $query->where('role', 'instructor'))
                ->first();
            if (! $instructor) {
                return back()->withErrors(['user_id' => 'Select an active instructor with an instructor profile.']);
            }
            if ($subjectOffering->instructor_id === $instructor->instructor_id) {
                return back()->withErrors(['user_id' => 'This instructor is already assigned to the offering.']);
            }
            DB::transaction(function () use ($subjectOffering, $instructor) {
                $subjectOffering->update(['instructor_id' => $instructor->instructor_id]);
                $this->syncLinkedInstructor($subjectOffering, $instructor->instructor_id);
                $this->log('update', 'subject_offerings', "Assigned instructor to {$subjectOffering->subject?->subject_code} offering.");
            });
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['offering' => 'The instructor could not be assigned. Please try again.']);
        }

        return back()->with('success', 'Instructor assigned to the subject offering.');
    }

    // @function removeOfferingInstructor: Tinatanggal ang offering instructor sa Subject flow.
    // @useIn removeOfferingInstructor: routes/web.php:330 (subjects.offerings.instructor.remove)
    public function removeOfferingInstructor(SubjectOffering $subjectOffering)
    {
        $subjectOffering->loadMissing(['academicYear', 'subject']);
        if (! $subjectOffering->isWritable()) {
            return back()->withErrors(['offering' => 'A closed or archived subject offering cannot be changed.']);
        }
        try {
            DB::transaction(function () use ($subjectOffering) {
                $subjectOffering->update(['instructor_id' => null]);
                $this->syncLinkedInstructor($subjectOffering, null);
                $this->log('update', 'subject_offerings', "Removed instructor from {$subjectOffering->subject?->subject_code} offering.");
            });
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['offering' => 'The instructor could not be removed. Please try again.']);
        }

        return back()->with('success', 'Instructor removed from the subject offering.');
    }

    private function syncLinkedInstructor(SubjectOffering $offering, ?int $instructorId): void
    {
        $scheduleIds = DB::table('schedules')
            ->where('subject_offering_id', $offering->subject_offering_id)
            ->select('scheduled_id');
        $now = now();

        DB::table('online_classes')
            ->whereIn('schedule_id', $scheduleIds)
            ->where('status', 'scheduled')
            ->whereNull('deleted_at')
            ->where(function ($query) use ($now) {
                $query->whereDate('scheduled_date', '>', $now->toDateString())
                    ->orWhere(function ($today) use ($now) {
                        $today->whereDate('scheduled_date', $now->toDateString())
                            ->where('end_time', '>', $now->format('H:i:s'));
                    });
            })
            ->update(['instructor_id' => $instructorId, 'updated_at' => $now]);

        DB::table('schedules')
            ->where('subject_offering_id', $offering->subject_offering_id)
            ->update(['instructor_id' => $instructorId]);
    }

    // @function syncOfferingFromLegacyFields: Sini-sync ang offering from legacy fields sa Subject flow.
    // @useIn syncOfferingFromLegacyFields: SubjectController::store (app/Http/Controllers/Admin/Subjects/SubjectController.php)
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

        $offeringSemester = $validated['semester'] ?: $section->semester;
        if (! $this->writableOfferingAcademicYearIds()->contains((int) $section->academic_year_id)) {
            throw ValidationException::withMessages([
                'section_id' => 'Subject offerings must use a draft or active academic year.',
            ]);
        }
        if ($offeringSemester !== $section->semester) {
            throw ValidationException::withMessages([
                'semester' => "Subject offerings for this section must use {$section->semester}.",
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

    // @function instructorIdForUser: Kinukuha ang instructor id for user result para sa Subject.
    // @useIn instructorIdForUser: SubjectController::storeOffering (app/Http/Controllers/Admin/Subjects/SubjectController.php)
    private function instructorIdForUser(int|string|null $userId): ?int
    {
        if (! $userId) {
            return null;
        }

        return Instructor::query()->where('user_id', $userId)->value('instructor_id');
    }

    // @function writableOfferingAcademicYearIds: Kinukuha ang writable offering academic year ids result para sa Subject.
    // @useIn writableOfferingAcademicYearIds: SubjectController::indexAdmin (app/Http/Controllers/Admin/Subjects/SubjectController.php)
    private function writableOfferingAcademicYearIds()
    {
        return AcademicYear::query()
            ->whereIn('status', [AcademicYear::STATUS_DRAFT, AcademicYear::STATUS_ACTIVE])
            ->orderByDesc('starts_on')
            ->pluck('academic_year_id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    // @function log: Nilolog ang subject sa Subject flow.
    // @useIn log: SubjectController::store (app/Http/Controllers/Admin/Subjects/SubjectController.php)
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
