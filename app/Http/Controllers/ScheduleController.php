<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ScheduleController
{
    public function indexAdmin(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'room' => trim((string) $request->input('room', '')),
        ];

        $query = Schedule::query()->with(['section', 'subject']);

        if ($filters['search'] !== '') {
            $term = strtolower($filters['search']);
            $query->where(function ($scheduleQuery) use ($term) {
                $scheduleQuery
                    ->whereRaw('LOWER(weekdays) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(room) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(subject_code) LIKE ?', ["%{$term}%"]);
            });
        }

        if ($filters['room'] !== '') {
            $query->where('room', $filters['room']);
        }

        return Inertia::render('Auth/Admin/Schedules', [
            'schedules' => $query->orderByDesc('timestamp')->get()->map(fn (Schedule $schedule) => [
                'scheduled_id' => $schedule->scheduled_id,
                'section_id' => $schedule->section_id,
                'section_name' => $schedule->section?->section_name,
                'subject_code' => $schedule->subject_code,
                'subject_name' => $schedule->subject?->subject_name,
                'weekdays' => $schedule->weekdays,
                'time_start' => $schedule->time_start,
                'time_end' => $schedule->time_end,
                'room' => $schedule->room,
                'timestamp' => $schedule->timestamp?->format('Y-m-d H:i:s'),
            ])->values(),
            'filters' => $filters,
            'sectionOptions' => Section::query()->orderBy('section_name')->get(['section_id', 'section_name'])->values(),
            'subjectOptions' => Subject::query()->orderBy('subject_code')->get(['subject_code', 'subject_name'])->values(),
            'roomOptions' => Schedule::query()->whereNotNull('room')->distinct()->orderBy('room')->pluck('room')->values(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'section_id' => 'required|exists:sections,section_id',
            'subject_code' => 'required|exists:subjects,subject_code',
            'weekdays' => 'required|string|max:255',
            'time_start' => 'required|date_format:H:i',
            'time_end' => 'required|date_format:H:i',
            'room' => 'required|string|max:255',
        ]);

        $schedule = Schedule::create($validated + [
            'timestamp' => Carbon::now(),
            'time_start' => $validated['time_start'] . ':00',
            'time_end' => $validated['time_end'] . ':00',
        ]);

        $this->log('create', 'schedules', 'Created schedule ' . $schedule->scheduled_id);

        return back()->with('success', 'Schedule added successfully.');
    }

    public function update(Request $request, int $id)
    {
        $schedule = Schedule::findOrFail($id);

        $validated = $request->validate([
            'section_id' => 'required|exists:sections,section_id',
            'subject_code' => 'required|exists:subjects,subject_code',
            'weekdays' => 'required|string|max:255',
            'time_start' => 'required|date_format:H:i',
            'time_end' => 'required|date_format:H:i',
            'room' => 'required|string|max:255',
        ]);

        $schedule->update($validated + [
            'timestamp' => Carbon::now(),
            'time_start' => $validated['time_start'] . ':00',
            'time_end' => $validated['time_end'] . ':00',
        ]);

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
            'user_id' => Auth::id(),
            'action' => $action,
            'table_name' => $tableName,
            'description' => $description,
        ]);
    }
}