<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Instructor;
use App\Models\Laboratory;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $query = Schedule::query()->with(['laboratory', 'instructor.user', 'section', 'subject']);

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
            'filters' => ['laboratory_id' => $laboratoryId],
            'currentUserRole' => $role,
            'canManageSchedules' => $isAdmin,
            'laboratories' => $isAdmin
                ? Laboratory::query()->orderBy('name')->get(['laboratory_id', 'name', 'status'])->values()
                : [],
            'sectionOptions' => $isAdmin
                ? Section::query()->orderBy('section_name')->get(['section_id', 'section_name'])->values()
                : [],
            'subjectOptions' => $isAdmin
                ? Subject::query()->orderBy('subject_code')->get(['subject_code', 'subject_name'])->values()
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
            'laboratory_id' => 'nullable|exists:laboratories,laboratory_id',
            'instructor_id' => 'nullable|exists:instructors,instructor_id',
            'section_id'    => 'required|exists:sections,section_id',
            'subject_code'  => 'required|exists:subjects,subject_code',
            'weekdays'      => 'required|string|max:255',
            'time_start'    => 'required|date_format:H:i',
            'time_end'      => 'required|date_format:H:i',
            'room'          => 'nullable|string|max:255',
        ]);

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

        $validated = $request->validate([
            'laboratory_id' => 'nullable|exists:laboratories,laboratory_id',
            'instructor_id' => 'nullable|exists:instructors,instructor_id',
            'section_id'    => 'required|exists:sections,section_id',
            'subject_code'  => 'required|exists:subjects,subject_code',
            'weekdays'      => 'required|string|max:255',
            'time_start'    => 'required|date_format:H:i',
            'time_end'      => 'required|date_format:H:i',
            'room'          => 'nullable|string|max:255',
        ]);

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
        $scheduleId = $schedule->scheduled_id;
        $schedule->delete();
        $this->log('delete', 'schedules', 'Deleted schedule ' . $scheduleId);

        return back()->with('success', 'Schedule deleted successfully.');
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
