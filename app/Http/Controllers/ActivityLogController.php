<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\OnlineClass;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\StudentEnrollment;
use App\Models\SubjectOffering;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;

class ActivityLogController
{
    private const FILTERS = [
        'search', 'date_from', 'date_to', 'module', 'action', 'user', 'user_role',
        'outcome', 'severity', 'subject_type', 'subject_id', 'ip_address', 'academic_year_id', 'semester',
    ];

    public function indexAdmin(Request $request)
    {
        return Inertia::render('Auth/Admin/ActivityLogs', [
            'logs' => $this->filteredQuery($request)->paginate(25)->withQueryString(),
            'filters' => $request->only(self::FILTERS),
            'options' => [
                'academicYears' => AcademicYear::query()->orderByDesc('starts_on')->get(['academic_year_id', 'name', 'status', 'active_semester']),
                'modules' => $this->distinctOptions('module', 'table_name'),
                'actions' => $this->distinctOptions('action'),
                'roles' => $this->distinctOptions('user_role'),
                'outcomes' => $this->distinctOptions('outcome'),
                'severities' => $this->distinctOptions('severity'),
                'subjectTypes' => $this->distinctOptions('subject_type'),
            ],
        ]);
    }

    public function export(Request $request)
    {
        $filename = 'system-activity-logs-'.now()->format('Y-m-d-His').'.csv';

        return Response::streamDownload(function () use ($request) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Event ID', 'Timestamp', 'User', 'Role', 'Module', 'Action', 'Outcome', 'Severity', 'Subject Type', 'Subject ID', 'IP Address', 'HTTP Method', 'Status', 'Route', 'Description']);
            $this->filteredQuery($request)->chunk(500, function ($logs) use ($stream) {
                foreach ($logs as $log) {
                    fputcsv($stream, [
                        $log->event_id, $log->created_at?->toIso8601String(), $log->user?->name ?? $log->user_name ?? 'System',
                        $log->user_role, $log->module ?? $log->table_name, $log->action, $log->outcome,
                        $log->severity, $log->subject_type, $log->subject_id, $log->ip_address,
                        $log->http_method, $log->status_code, $log->route_name, $log->description,
                    ]);
                }
            });
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filteredQuery(Request $request): Builder
    {
        return ActivityLog::query()
            ->with('user:user_id,name,email')
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $term = '%'.strtolower(trim((string) $request->input('search'))).'%';
                $query->where(function (Builder $inner) use ($term) {
                    $inner->whereRaw('LOWER(description) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(action) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(COALESCE(module, table_name)) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(COALESCE(user_name, \'\')) LIKE ?', [$term])
                        ->orWhereHas('user', fn (Builder $user) => $user->whereRaw('LOWER(name) LIKE ?', [$term])->orWhereRaw('LOWER(email) LIKE ?', [$term]))
                        ->orWhere('event_id', 'like', $term)
                        ->orWhere('route_name', 'like', $term);
                });
            })
            ->when($request->filled('date_from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->input('date_to')))
            ->when($request->filled('module'), fn (Builder $query) => $query->where(fn (Builder $inner) => $inner->where('module', $request->input('module'))->orWhere(fn (Builder $legacy) => $legacy->whereNull('module')->where('table_name', $request->input('module')))))
            ->when($request->filled('action'), fn (Builder $query) => $query->where('action', $request->input('action')))
            ->when($request->filled('user'), fn (Builder $query) => $query->where('user_id', $request->input('user')))
            ->when($request->filled('user_role'), fn (Builder $query) => $query->where('user_role', $request->input('user_role')))
            ->when($request->filled('outcome'), fn (Builder $query) => $query->where('outcome', $request->input('outcome')))
            ->when($request->filled('severity'), fn (Builder $query) => $query->where('severity', $request->input('severity')))
            ->when($request->filled('subject_type'), fn (Builder $query) => $query->where('subject_type', $request->input('subject_type')))
            ->when($request->filled('subject_id'), fn (Builder $query) => $query->where('subject_id', $request->input('subject_id')))
            ->when($request->filled('ip_address'), fn (Builder $query) => $query->where('ip_address', $request->input('ip_address')))
            ->when($request->filled('academic_year_id') || $request->filled('semester'), function (Builder $query) use ($request) {
                $yearId = $request->integer('academic_year_id') ?: null;
                $semester = trim((string) $request->input('semester', ''));
                $contexts = [];
                if ($yearId) {
                    $contexts[AcademicYear::class] = AcademicYear::query()->whereKey($yearId)->select('academic_year_id');
                }
                $contexts += [
                    Section::class => Section::query()->when($yearId, fn ($q) => $q->where('academic_year_id', $yearId))->when($semester !== '', fn ($q) => $q->where('semester', $semester))->select('section_id'),
                    SubjectOffering::class => SubjectOffering::query()->when($yearId, fn ($q) => $q->where('academic_year_id', $yearId))->when($semester !== '', fn ($q) => $q->where('semester', $semester))->select('subject_offering_id'),
                    Schedule::class => Schedule::query()->when($yearId, fn ($q) => $q->where('academic_year_id', $yearId))->when($semester !== '', fn ($q) => $q->where('semester', $semester))->select('scheduled_id'),
                    StudentEnrollment::class => StudentEnrollment::query()->when($yearId, fn ($q) => $q->where('academic_year_id', $yearId))->when($semester !== '', fn ($q) => $q->where('semester', $semester))->select('student_enrollment_id'),
                    Attendance::class => Attendance::query()->when($yearId, fn ($q) => $q->where('academic_year_id', $yearId))->when($semester !== '', fn ($q) => $q->whereHas('schedule', fn ($schedule) => $schedule->where('semester', $semester)))->select('attendance_id'),
                    OnlineClass::class => OnlineClass::query()->when($yearId, fn ($q) => $q->where('academic_year_id', $yearId))->when($semester !== '', fn ($q) => $q->whereHas('schedule', fn ($schedule) => $schedule->where('semester', $semester)))->select('online_class_id'),
                ];
                $query->where(function (Builder $contextQuery) use ($contexts) {
                    foreach ($contexts as $type => $ids) {
                        $contextQuery->orWhere(fn (Builder $match) => $match->where('subject_type', $type)->whereIn('subject_id', $ids));
                    }
                });
            })
            ->latest('created_at')->latest('logs_id');
    }

    private function distinctOptions(string $column, ?string $fallback = null)
    {
        $primary = ActivityLog::query()->whereNotNull($column)->where($column, '<>', '')->pluck($column);
        if ($fallback) {
            $primary = $primary->merge(ActivityLog::query()->whereNull($column)->whereNotNull($fallback)->pluck($fallback));
        }

        return $primary->unique()->sort()->values();
    }
}
