<?php

namespace App\Http\Controllers;

use App\Models\Instructor;
use App\Models\OnlineClass;
use App\Models\OnlineClassAttendance;
use App\Models\OnlineClassAuditLog;
use App\Models\Schedule;
use App\Models\Students;
use App\Models\SystemSetting;
use App\Services\OnlineClassAuditLogger;
use App\Services\OnlineClassNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class OnlineClassController
{
    public function __construct(
        private OnlineClassAuditLogger $auditLogger,
        private OnlineClassNotificationService $notificationService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $role = strtolower(trim((string) $user?->role));
        $instructorId = $this->instructorId($user?->user_id);

        $classes = OnlineClass::query()
            ->with(['section', 'subject', 'instructor.user', 'attachments'])
            ->when($role === 'instructor', fn ($query) => $query->where('instructor_id', $instructorId ?: 0))
            ->orderByDesc('scheduled_date')
            ->orderByDesc('start_time')
            ->get()
            ->map(fn (OnlineClass $onlineClass) => $this->classPayload($onlineClass))
            ->values();

        return Inertia::render('Auth/Admin/OnlineClasses', [
            'title' => 'Online Classes',
            'onlineClasses' => $classes,
            'scheduleOptions' => $this->scheduleOptions($role, $instructorId),
            'defaultRequireFaceRecognition' => SystemSetting::boolean(
                SystemSetting::ONLINE_CLASS_FACE_RECOGNITION_DEFAULT,
                true,
            ),
            'currentUserRole' => $role,
        ]);
    }

    public function store(Request $request)
    {
        $schedule = $this->authorizedSchedule($request, (int) $request->input('schedule_id'));

        $validated = $this->validatedClassData($request);
        unset($validated['schedule_id'], $validated['attachments']);
        $onlineClass = OnlineClass::query()->create([
            ...$validated,
            'schedule_id' => $schedule->scheduled_id,
            'instructor_id' => $schedule->instructor_id,
            'section_id' => $schedule->section_id,
            'subject_code' => $schedule->subject_code,
            'created_by_user_id' => $request->user()->user_id,
            'updated_by_user_id' => $request->user()->user_id,
        ]);

        $this->storeAttachments($request, $onlineClass);
        $this->auditLogger->log('online_class_created', $onlineClass, $request->user(), $request, null, $onlineClass->fresh()->toArray());
        $this->notificationService->notifyStudents($onlineClass->fresh(['section', 'subject', 'instructor.user']), 'created');

        return back()->with('success', 'Online class created.');
    }

    public function update(Request $request, OnlineClass $onlineClass)
    {
        $this->authorizeManage($request, $onlineClass);
        $previous = $onlineClass->toArray();
        $schedule = $this->authorizedSchedule($request, (int) $request->input('schedule_id'));

        $validated = $this->validatedClassData($request);
        unset($validated['schedule_id'], $validated['attachments']);
        $onlineClass->update([
            ...$validated,
            'schedule_id' => $schedule->scheduled_id,
            'instructor_id' => $schedule->instructor_id,
            'section_id' => $schedule->section_id,
            'subject_code' => $schedule->subject_code,
            'updated_by_user_id' => $request->user()->user_id,
        ]);

        $this->storeAttachments($request, $onlineClass);
        $action = $this->isRescheduled($previous, $onlineClass->fresh()->toArray())
            ? 'online_class_rescheduled'
            : 'online_class_updated';

        $this->auditLogger->log($action, $onlineClass, $request->user(), $request, $previous, $onlineClass->fresh()->toArray());
        if ((bool) $previous['require_face_recognition'] !== (bool) $onlineClass->require_face_recognition) {
            $this->auditLogger->log('face_requirement_changed', $onlineClass, $request->user(), $request, [
                'require_face_recognition' => $previous['require_face_recognition'],
            ], [
                'require_face_recognition' => $onlineClass->require_face_recognition,
            ]);
        }
        $this->notificationService->notifyStudents($onlineClass->fresh(['section', 'subject', 'instructor.user']), $action === 'online_class_rescheduled' ? 'rescheduled' : 'updated');

        return back()->with('success', 'Online class updated.');
    }

    public function cancel(Request $request, OnlineClass $onlineClass)
    {
        $this->authorizeManage($request, $onlineClass);
        $previous = $onlineClass->toArray();
        $onlineClass->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $request->user()->user_id,
        ]);

        $this->auditLogger->log('online_class_cancelled', $onlineClass, $request->user(), $request, $previous, $onlineClass->fresh()->toArray());
        $this->notificationService->notifyStudents($onlineClass->fresh(['section', 'subject', 'instructor.user']), 'cancelled');

        return back()->with('success', 'Online class cancelled.');
    }

    public function destroy(Request $request, OnlineClass $onlineClass)
    {
        $this->authorizeManage($request, $onlineClass);
        $previous = $onlineClass->toArray();
        $onlineClass->delete();
        $this->auditLogger->log('online_class_deleted', $onlineClass, $request->user(), $request, $previous, null);

        return back()->with('success', 'Online class deleted.');
    }

    public function studentIndex(Request $request)
    {
        $student = $this->currentStudent($request);
        abort_unless($student, 403);

        $classes = OnlineClass::query()
            ->with(['section', 'subject', 'instructor.user', 'attachments', 'attendances' => fn ($query) => $query->where('student_id', $student->student_id)])
            ->where('section_id', $student->section_id)
            ->orderByDesc('scheduled_date')
            ->orderByDesc('start_time')
            ->get()
            ->map(fn (OnlineClass $onlineClass) => $this->classPayload($onlineClass, $student))
            ->values();

        return Inertia::render('StudentParent/OnlineClasses', [
            'title' => 'Online Classes',
            'onlineClasses' => $classes,
        ]);
    }

    public function join(Request $request, OnlineClass $onlineClass)
    {
        $student = $this->currentStudent($request);
        abort_unless($student && (int) $student->section_id === (int) $onlineClass->section_id, 403);
        abort_if($onlineClass->status === 'cancelled', 422, 'This online class is cancelled.');

        $validated = $request->validate([
            'face_verified' => ['nullable', 'boolean'],
        ]);

        if ($onlineClass->require_face_recognition && ! (bool) ($validated['face_verified'] ?? false)) {
            $this->auditLogger->log('student_failed_face_recognition', $onlineClass, $request->user(), $request, null, [
                'student_id' => $student->student_id,
            ]);
            abort(422, 'Facial recognition is required before joining this class.');
        }

        $joinedAt = now();
        $isLate = $joinedAt->greaterThan(Carbon::parse($onlineClass->scheduled_date->format('Y-m-d').' '.$onlineClass->start_time));
        $attendance = OnlineClassAttendance::query()->updateOrCreate(
            [
                'online_class_id' => $onlineClass->online_class_id,
                'student_id' => $student->student_id,
            ],
            [
                'joined_at' => $joinedAt,
                'status' => $isLate ? 'late' : 'present',
                'is_late' => $isLate,
                'face_required' => $onlineClass->require_face_recognition,
                'face_verified' => $onlineClass->require_face_recognition ? true : ($validated['face_verified'] ?? null),
                'face_verified_at' => $onlineClass->require_face_recognition ? $joinedAt : null,
            ],
        );

        $this->auditLogger->log('student_joined_online_class', $onlineClass, $request->user(), $request, null, [
            'student_id' => $student->student_id,
            'attendance_id' => $attendance->online_class_attendance_id,
        ]);
        $this->auditLogger->log('attendance_recorded', $onlineClass, $request->user(), $request, null, $attendance->toArray());
        if ($onlineClass->require_face_recognition) {
            $this->auditLogger->log('student_passed_face_recognition', $onlineClass, $request->user(), $request, null, [
                'student_id' => $student->student_id,
            ]);
        }

        return back()->with('success', 'Online class attendance recorded.');
    }

    public function logs(Request $request)
    {
        return Inertia::render('Auth/Admin/OnlineClassLogs', [
            'title' => 'Online Class Logs',
            'logs' => $this->logQuery($request)->paginate(20)->withQueryString(),
            'filters' => $request->only(['search', 'date_from', 'date_to', 'instructor', 'user', 'user_role', 'section', 'action']),
        ]);
    }

    public function exportLogs(Request $request)
    {
        $rows = $this->logQuery($request)->get();
        $csv = "Timestamp,User,Role,Action,Online Class ID,Section,IP Address\n";
        foreach ($rows as $log) {
            $csv .= implode(',', array_map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"', [
                $log->created_at?->toDateTimeString(),
                $log->user?->name,
                $log->user_role,
                $log->action,
                $log->online_class_id,
                $log->section?->section_name,
                $log->ip_address,
            ]))."\n";
        }

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="online-class-logs.csv"',
        ]);
    }

    private function validatedClassData(Request $request): array
    {
        return $request->validate([
            'schedule_id' => ['required', 'integer', 'exists:schedules,scheduled_id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'meeting_link' => ['required', 'url', 'max:2048'],
            'scheduled_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'require_face_recognition' => ['required', 'boolean'],
            'attachments.*' => ['nullable', 'file', 'max:10240'],
        ]);
    }

    private function authorizedSchedule(Request $request, int $scheduleId): Schedule
    {
        $schedule = Schedule::query()->findOrFail($scheduleId);
        $role = strtolower(trim((string) $request->user()?->role));
        if ($role === 'instructor') {
            abort_unless((int) $schedule->instructor_id === (int) $this->instructorId($request->user()->user_id), 403);
        }

        return $schedule;
    }

    private function authorizeManage(Request $request, OnlineClass $onlineClass): void
    {
        $role = strtolower(trim((string) $request->user()?->role));
        if ($role === 'admin') {
            return;
        }

        abort_unless((int) $onlineClass->instructor_id === (int) $this->instructorId($request->user()->user_id), 403);
    }

    private function scheduleOptions(string $role, ?int $instructorId)
    {
        return Schedule::query()
            ->with(['section', 'subject'])
            ->when($role === 'instructor', fn ($query) => $query->where('instructor_id', $instructorId ?: 0))
            ->orderBy('weekdays')
            ->orderBy('time_start')
            ->get()
            ->map(fn (Schedule $schedule) => [
                'scheduled_id' => $schedule->scheduled_id,
                'label' => trim(($schedule->section?->section_name ?? 'Section').' - '.($schedule->subject?->subject_name ?? $schedule->subject_code).' - '.$schedule->weekdays.' '.$schedule->time_start),
                'section_name' => $schedule->section?->section_name,
                'subject_name' => $schedule->subject?->subject_name,
            ])
            ->values();
    }

    private function classPayload(OnlineClass $onlineClass, ?Students $student = null): array
    {
        $attendance = $student ? $onlineClass->attendances->first() : null;

        return [
            'online_class_id' => $onlineClass->online_class_id,
            'schedule_id' => $onlineClass->schedule_id,
            'section_name' => $onlineClass->section?->section_name,
            'subject_name' => $onlineClass->subject?->subject_name ?? $onlineClass->subject_code,
            'instructor_name' => $onlineClass->instructor?->user?->name,
            'title' => $onlineClass->title,
            'description' => $onlineClass->description,
            'meeting_link' => $onlineClass->meeting_link,
            'scheduled_date' => $onlineClass->scheduled_date?->format('Y-m-d'),
            'start_time' => substr((string) $onlineClass->start_time, 0, 5),
            'end_time' => substr((string) $onlineClass->end_time, 0, 5),
            'require_face_recognition' => $onlineClass->require_face_recognition,
            'status' => $onlineClass->status,
            'attendance_status' => $attendance?->status,
            'face_verified' => $attendance?->face_verified,
            'attachments' => $onlineClass->attachments->map(fn ($attachment) => [
                'name' => $attachment->file_name,
                'url' => Storage::disk('public')->url($attachment->file_path),
            ])->values(),
        ];
    }

    private function currentStudent(Request $request): ?Students
    {
        return Students::query()->where('email', $request->user()?->email)->first();
    }

    private function instructorId(?int $userId): ?int
    {
        return Instructor::query()->where('user_id', $userId)->value('instructor_id');
    }

    private function storeAttachments(Request $request, OnlineClass $onlineClass): void
    {
        foreach ($request->file('attachments', []) as $file) {
            $onlineClass->attachments()->create([
                'file_path' => $file->store('online-class-attachments', 'public'),
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }
    }

    private function isRescheduled(array $previous, array $current): bool
    {
        return $previous['scheduled_date'] !== $current['scheduled_date']
            || $previous['start_time'] !== $current['start_time']
            || $previous['end_time'] !== $current['end_time'];
    }

    private function logQuery(Request $request)
    {
        return OnlineClassAuditLog::query()
            ->with(['user:user_id,name,email', 'section:section_id,section_name', 'onlineClass.instructor.user:user_id,name,email'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = strtolower((string) $request->input('search'));
                $query->where(function ($inner) use ($search) {
                    $inner->whereRaw('LOWER(action) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(user_role) LIKE ?', ["%{$search}%"]);
                });
            })
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->input('date_to')))
            ->when($request->filled('instructor'), fn ($query) => $query->whereHas('onlineClass', fn ($classQuery) => $classQuery->where('instructor_id', $request->input('instructor'))))
            ->when($request->filled('user'), fn ($query) => $query->where('user_id', $request->input('user')))
            ->when($request->filled('user_role'), fn ($query) => $query->where('user_role', $request->input('user_role')))
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->input('action')))
            ->when($request->filled('section'), fn ($query) => $query->where('section_id', $request->input('section')))
            ->latest('created_at');
    }
}
