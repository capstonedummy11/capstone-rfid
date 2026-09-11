<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AcademicYear;
use App\Models\Instructor;
use App\Models\Laboratory;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Subject;
use App\Models\SubjectOffering;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ScheduleController
{
    public function indexAdmin(Request $request)
    {
        $user = $request->user();
        $role = strtolower(trim((string) $user?->role));
        $isAdmin = $role === 'admin';
        $isInstructor = $role === 'instructor';
        $instructorId = $isInstructor
            ? Instructor::query()->where('user_id', $user?->user_id)->value('instructor_id')
            : null;
        $laboratoryId = $isAdmin && $request->input('laboratory_id') ? (int) $request->input('laboratory_id') : null;
        $defaultYearId = AcademicYear::currentOrLatest()?->academic_year_id;
        $currentAcademicYear = AcademicYear::currentOrLatest();
        $currentSemester = $currentAcademicYear?->active_semester;
        $requestedYear = $request->input('academic_year_id');
        $academicYearId = $requestedYear === 'all' ? null : ($request->integer('academic_year_id') ?: $defaultYearId);
        $semester = trim((string) $request->input('semester', ''));
        if ($semester === '' && $academicYearId) {
            $semester = AcademicYear::find($academicYearId)?->active_semester ?: '';
        }

        $query = Schedule::query()->with(['laboratory', 'instructor.user', 'section', 'subject', 'academicYear', 'subjectOffering']);
        $query->when($academicYearId, fn ($yearQuery) => $yearQuery->where('academic_year_id', $academicYearId));
        $query->when($semester !== '', fn ($termQuery) => $termQuery->where('semester', $semester));

        if ($isInstructor) {
            $query->where('instructor_id', $instructorId ?: 0);
        } elseif ($laboratoryId !== null) {
            $query->where('laboratory_id', $laboratoryId);
        }

        return Inertia::render('Auth/Admin/Schedules', [
            'schedules' => $query
                ->orderBy('room')
                ->orderBy('weekdays')
                ->orderBy('time_start')
                ->get()
                ->map(fn (Schedule $schedule) => [
                'scheduled_id'   => $schedule->scheduled_id,
                'academic_year_id' => $schedule->academic_year_id,
                'academic_year_name' => $schedule->academicYear?->name,
                'academic_year_status' => $schedule->academicYear?->status,
                'subject_offering_id' => $schedule->subject_offering_id,
                'semester' => $schedule->semester,
                'is_writable' => $schedule->academicYear?->isWritable() ?? true,
                'laboratory_id'  => $schedule->laboratory_id,
                'laboratory_name' => $schedule->laboratory?->name,
                'instructor_id'  => $schedule->instructor_id,
                'instructor_name' => $schedule->instructor?->user?->name,
                'section_id'     => $schedule->section_id,
                'section_name'   => $schedule->section?->section_name,
                'subject_code'   => $schedule->subject_code,
                'subject_name'   => $schedule->subject?->subject_name,
                'weekdays'       => $schedule->weekdays,
                'time_start'     => $schedule->time_start,
                'time_end'       => $schedule->time_end,
                'room'           => $schedule->room,
            ])->values(),
            'filters' => ['laboratory_id' => $laboratoryId, 'academic_year_id' => $requestedYear === 'all' ? 'all' : $academicYearId, 'semester' => $semester],
            'academicYears' => AcademicYear::query()->orderByDesc('starts_on')->get(['academic_year_id', 'name', 'status']),
            'currentUserRole' => $role,
            'canManageSchedules' => $isAdmin,
            'laboratories' => $isAdmin
                ? Laboratory::query()->orderBy('name')->get(['laboratory_id', 'name', 'status'])->values()
                : [],
            'sectionOptions' => $isAdmin
                ? Section::query()
                    ->when($defaultYearId, fn ($query) => $query->where('academic_year_id', $defaultYearId))
                    ->when($currentSemester, fn ($query) => $query->where('semester', $currentSemester))
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
                    ->values()
                : [],
            'subjectOptions' => $isAdmin
                ? Subject::query()->orderBy('subject_code')->get(['subject_code', 'subject_name'])->values()
                : [],
            'subjectOfferingOptions' => $isAdmin
                ? SubjectOffering::query()
                    ->with(['academicYear', 'subject', 'section', 'instructor.user'])
                    ->whereHas('academicYear', fn ($query) => $query->whereIn('status', ['draft', 'active']))
                    ->when($defaultYearId, fn ($query) => $query->where('academic_year_id', $defaultYearId))
                    ->when($currentSemester, fn ($query) => $query->where('semester', $currentSemester))
                    ->orderBy('subject_offering_id')
                    ->get()
                    ->map(fn (SubjectOffering $offering) => [
                        'subject_offering_id' => $offering->subject_offering_id,
                        'academic_year_id' => $offering->academic_year_id,
                        'academic_year' => $offering->academicYear?->name,
                        'semester' => $offering->semester,
                        'section_id' => $offering->section_id,
                        'section_name' => $offering->section?->section_name,
                        'subject_code' => $offering->subject?->subject_code,
                        'subject_name' => $offering->subject?->subject_name,
                        'instructor_id' => $offering->instructor_id,
                        'instructor_name' => $offering->instructor?->user?->name,
                        'label' => implode(' · ', array_filter([
                            $offering->subject?->subject_code.' - '.$offering->subject?->subject_name,
                            $offering->section?->section_name,
                            $offering->academicYear?->name,
                            $offering->semester,
                            $offering->instructor?->user?->name,
                        ])),
                    ])->values()
                : [],
            'instructorOptions' => $isAdmin ? Instructor::query()->with('user')->get()->map(fn (Instructor $i) => [
                'instructor_id' => $i->instructor_id,
                'name'          => $i->user?->name ?? '(No name)',
            ])->sortBy('name')->values() : [],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject_offering_id' => 'nullable|exists:subject_offerings,subject_offering_id',
            'laboratory_id' => 'nullable|exists:laboratories,laboratory_id',
            'instructor_id' => 'nullable|exists:instructors,instructor_id',
            'section_id'    => 'nullable|required_without:subject_offering_id|exists:sections,section_id',
            'subject_code'  => 'nullable|required_without:subject_offering_id|exists:subjects,subject_code',
            'weekdays'      => 'required|string|max:255',
            'time_start'    => 'required|date_format:H:i',
            'time_end'      => 'required|date_format:H:i',
            'room'          => 'nullable|string|max:255',
        ]);

        $validated = $this->resolveOffering($validated);

        $schedule = Schedule::create(array_merge($validated, [
            'time_start' => $validated['time_start'] . ':00',
            'time_end'   => $validated['time_end'] . ':00',
        ]));

        $this->log('create', 'schedules', 'Created schedule ' . $schedule->scheduled_id);

        return back()->with('success', 'Schedule added successfully.');
    }

    public function update(Request $request, int $id)
    {
        $schedule = Schedule::findOrFail($id);
        if ($schedule->academicYear && ! $schedule->academicYear->isWritable()) {
            return back()->withErrors(['schedule' => 'A schedule from a closed or archived academic year cannot be edited.']);
        }

        $validated = $request->validate([
            'subject_offering_id' => 'nullable|exists:subject_offerings,subject_offering_id',
            'laboratory_id' => 'nullable|exists:laboratories,laboratory_id',
            'instructor_id' => 'nullable|exists:instructors,instructor_id',
            'section_id'    => 'nullable|required_without:subject_offering_id|exists:sections,section_id',
            'subject_code'  => 'nullable|required_without:subject_offering_id|exists:subjects,subject_code',
            'weekdays'      => 'required|string|max:255',
            'time_start'    => 'required|date_format:H:i',
            'time_end'      => 'required|date_format:H:i',
            'room'          => 'nullable|string|max:255',
        ]);

        $validated = $this->resolveOffering($validated);

        $schedule->update(array_merge($validated, [
            'time_start' => $validated['time_start'] . ':00',
            'time_end'   => $validated['time_end'] . ':00',
        ]));

        $this->log('update', 'schedules', 'Updated schedule ' . $schedule->scheduled_id);

        return back()->with('success', 'Schedule updated successfully.');
    }

    public function destroy(int $id)
    {
        $schedule = Schedule::findOrFail($id);
        if ($schedule->academicYear && ! $schedule->academicYear->isWritable()) {
            return back()->withErrors(['schedule' => 'A schedule from a closed or archived academic year cannot be deleted.']);
        }
        if ($schedule->attendances()->exists() || $schedule->onlineClasses()->exists()) {
            return back()->withErrors(['schedule' => 'This schedule has attendance or online-class history and cannot be deleted.']);
        }
        $scheduleId = $schedule->scheduled_id;
        $schedule->delete();
        $this->log('delete', 'schedules', 'Deleted schedule ' . $scheduleId);

        return back()->with('success', 'Schedule deleted successfully.');
    }

    private function resolveOffering(array $validated): array
    {
        if (empty($validated['subject_offering_id'])) {
            $section = Section::query()->with('academicYear')->findOrFail($validated['section_id']);
            if ($section->academicYear && ! $section->academicYear->isWritable()) {
                throw ValidationException::withMessages([
                    'subject_offering_id' => 'Schedules cannot be created or changed in a closed or archived academic year.',
                ]);
            }

            $this->assertCurrentAcademicContext($section->academic_year_id, $section->semester);

            $subjectId = Subject::query()->where('subject_code', $validated['subject_code'])->value('subject_id');
            $offering = SubjectOffering::query()
                ->where('academic_year_id', $section->academic_year_id)
                ->where('section_id', $section->section_id)
                ->where('subject_id', $subjectId ?: 0)
                ->when($validated['instructor_id'] ?? null, fn ($query, $instructorId) => $query->where('instructor_id', $instructorId))
                ->first();

            return $validated + [
                'academic_year_id' => $section->academic_year_id,
                'subject_offering_id' => $offering?->subject_offering_id,
                'semester' => $offering?->semester ?: $section->semester,
            ];
        }

        $offering = SubjectOffering::query()
            ->with(['academicYear', 'subject', 'section'])
            ->findOrFail($validated['subject_offering_id']);
        if (! $offering->isWritable()) {
            throw ValidationException::withMessages([
                'subject_offering_id' => 'Schedules can only use offerings from a draft or active academic year.',
            ]);
        }
        $this->assertCurrentAcademicContext($offering->academic_year_id, $offering->semester);

        return array_merge($validated, [
            'academic_year_id' => $offering->academic_year_id,
            'section_id' => $offering->section_id,
            'subject_code' => $offering->subject?->subject_code,
            'instructor_id' => $offering->instructor_id,
            'semester' => $offering->semester,
        ]);
    }

    private function assertCurrentAcademicContext(int|string|null $academicYearId, ?string $semester): void
    {
        $currentAcademicYear = AcademicYear::currentOrLatest();
        if (! $currentAcademicYear || (int) $academicYearId !== (int) $currentAcademicYear->academic_year_id) {
            throw ValidationException::withMessages([
                'subject_offering_id' => 'Schedules can only use the current academic year.',
            ]);
        }

        if ($currentAcademicYear->active_semester && $semester !== $currentAcademicYear->active_semester) {
            throw ValidationException::withMessages([
                'subject_offering_id' => "Schedules can only use the current {$currentAcademicYear->active_semester}.",
            ]);
        }
    }

    private function log(string $action, string $tableName, string $description): void
    {
        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => $action,
            'table_name'  => $tableName,
            'description' => $description,
        ]);
    }
}
