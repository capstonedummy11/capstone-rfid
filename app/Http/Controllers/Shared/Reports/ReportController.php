<?php

namespace App\Http\Controllers\Shared\Reports;

use App\Models\AcademicYear;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Used by roles: Admin, Instructor, Clinic, Registrar, Student, and Parent; Parent access follows the portal setting.
class ReportController
{
    // @function index: Ibinabalik ang Reports/Index page at data para sa request.
    // @useIn index: routes/web.php:141 (reports.index)
    /**
     * @feature   Reports and Exports
     * @actor     Shared / Core
     * @flow      Dito fina-filter at ine-export ang role-scoped reports.
     * @uses      resources/js/pages/Shared/Reports/Index/IndexPage.vue; routes/web.php: ReportController::index, ReportController::export
     * @related   Authentication, Attendance, Reports
     * @disable   1) I-comment out ang routes/web.php: ReportController::index, ReportController::export.
     * @disable   2) Itago ang action sa resources/js/pages/Shared/Reports/Index/IndexPage.vue; kung may menu link, alisin ito sa resources/js/layouts/AuthNavbar.vue.
     * @disable   3) Ihinto ang app/Http/Controllers/Shared/Reports/ReportController.php: ReportController::index matapos alisin ang routes. Side effect: mawawala ang reports and exports.
     */
    public function index(Request $request)
    {
        return Inertia::render('Shared/Reports/Index/IndexPage', $this->reportPayload($request));
    }

    // @function export: Ine-export ang report sa Report flow.
    // @useIn export: routes/web.php:143 (reports.export)
    public function export(Request $request): StreamedResponse
    {
        $payload = $this->reportPayload($request);
        $filename = $payload['role'].'-report-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($payload) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Report', $payload['title']]);
            fputcsv($handle, ['Generated At', now()->format('Y-m-d H:i:s')]);
            fputcsv($handle, ['Date From', $payload['filters']['date_from'] ?: 'All']);
            fputcsv($handle, ['Date To', $payload['filters']['date_to'] ?: 'All']);
            fputcsv($handle, ['Academic Year', $payload['selectedAcademicYear']['name'] ?? 'All years']);
            fputcsv($handle, ['Semester', $payload['filters']['semester'] ?: 'All semesters']);
            fputcsv($handle, []);
            fputcsv($handle, ['Category', 'Metric', 'Value', 'Group']);

            foreach ($payload['tableRows'] as $row) {
                fputcsv($handle, [
                    $row['category'],
                    $row['metric'],
                    $row['value'],
                    $row['group'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    // @function reportPayload: Binubuo ang report payload value.
    // @useIn reportPayload: ReportController::index (app/Http/Controllers/Shared/Reports/ReportController.php)
    private function reportPayload(Request $request): array
    {
        $role = strtolower((string) $request->user()?->role);
        $filters = [
            'date_from' => $request->string('date_from')->toString(),
            'date_to' => $request->string('date_to')->toString(),
            'academic_year_id' => $this->resolvedAcademicYearId($request, $role),
            'semester' => in_array($request->input('semester'), ['1st Semester', '2nd Semester'], true) ? $request->input('semester') : '',
        ];

        return match ($role) {
            'clinic' => $this->clinicReport($filters),
            'registrar' => $this->registrarReport($filters),
            'instructor' => $this->instructorReport($request, $filters),
            'student' => $this->studentReport($request, $filters),
            'parent' => $this->parentReport($request, $filters),
            default => $this->adminReport($filters),
        };
    }

    // @function adminReport: Kinukuha ang admin report result para sa Report.
    // @useIn adminReport: ReportController::reportPayload (app/Http/Controllers/Shared/Reports/ReportController.php)
    private function adminReport(array $filters): array
    {
        $charts = [
            $this->chart('Users by Role', $this->grouped('users', 'role'), 'donut'),
            $this->chart('Students by Status', $this->grouped('students', 'status', $filters), 'bar'),
            $this->chart('Students by Year Level', $this->grouped('student_enrollments', 'year_level', $filters), 'donut'),
            $this->chart('Attendance by Status', $this->grouped('attendances', 'status', $filters, 'date'), 'bar'),
            $this->chart('Attendance Trend', $this->dateSeries('attendances', 'date', $filters), 'trend'),
            $this->chart('Borrowing by Status', $this->grouped('borrowings', 'status', $filters, 'borrowed_at'), 'bar'),
            $this->chart('Borrowing by Borrower Type', $this->grouped('borrowings', 'borrower_type', $filters, 'borrowed_at'), 'donut'),
            $this->chart('Inventory by Status', $this->grouped('inventory_items', 'status'), 'donut'),
            $this->chart('Clinic Cases by Status', $this->grouped('clinic_cases', 'status', $filters, 'occurred_at'), 'list'),
        ];

        return $this->payload('admin', 'Admin Reports', [
            $this->card('Users', $this->countTable('users')),
            $this->card('Students', $this->countTable('students', $filters)),
            $this->card('Attendance Records', $this->countTable('attendances', $filters, 'date')),
            $this->card('Borrowings', $this->countTable('borrowings', $filters, 'borrowed_at')),
            $this->card('Clinic Cases', $this->countTable('clinic_cases', $filters, 'occurred_at')),
        ], $charts, $filters);
    }

    // @function clinicReport: Kinukuha ang clinic report result para sa Report.
    // @useIn clinicReport: ReportController::reportPayload (app/Http/Controllers/Shared/Reports/ReportController.php)
    private function clinicReport(array $filters): array
    {
        $charts = [
            $this->chart('Clinic Cases by Status', $this->grouped('clinic_cases', 'status', $filters, 'occurred_at'), 'donut'),
            $this->chart('Clinic Cases by Type', $this->grouped('clinic_cases', 'case_type', $filters, 'occurred_at'), 'bar'),
            $this->chart('Clinic Case Trend', $this->dateSeries('clinic_cases', 'occurred_at', $filters), 'trend'),
            $this->chart('Emergency Alerts by Status', $this->grouped('emergency_alerts', 'status', $filters), 'donut'),
            $this->chart('Emergency Alerts by Severity', $this->grouped('emergency_alerts', 'severity', $filters), 'bar'),
            $this->chart('Emergency Alert Trend', $this->dateSeries('emergency_alerts', 'created_at', $filters), 'trend'),
            $this->chart('Patient Histories by Type', $this->grouped('patient_histories', 'patient_type', $filters, 'occurred_at'), 'list'),
        ];

        return $this->payload('clinic', 'Clinic Reports', [
            $this->card('Clinic Cases', $this->countTable('clinic_cases', $filters, 'occurred_at')),
            $this->card('Open Cases', $this->countTable('clinic_cases', $filters, 'occurred_at', fn (Builder $query) => $query->whereIn('status', ['open', 'monitoring']))),
            $this->card('Patient Histories', $this->countTable('patient_histories', $filters, 'occurred_at')),
            $this->card('Emergency Alerts', $this->countTable('emergency_alerts', $filters)),
            $this->card('Resolved Alerts', $this->countTable('emergency_alerts', $filters, 'created_at', fn (Builder $query) => $query->where('status', 'resolved'))),
        ], $charts, $filters);
    }

    // @function registrarReport: Kinukuha ang registrar report result para sa Report.
    // @useIn registrarReport: ReportController::reportPayload (app/Http/Controllers/Shared/Reports/ReportController.php)
    private function registrarReport(array $filters): array
    {
        $strandRows = $this->joinedStudentGroup('strands', 'strand_id', 'strand_code', $filters);
        $sectionRows = $this->joinedStudentGroup('sections', 'section_id', 'section_name', $filters);
        $charts = [
            $this->chart('Students by Strand', $strandRows, 'bar'),
            $this->chart('Students by Section', $sectionRows, 'list'),
            $this->chart('Students by Year Level', $this->grouped('student_enrollments', 'year_level', $filters), 'donut'),
            $this->chart('Students by Status', $this->grouped('student_enrollments', 'status', $filters), 'donut'),
            $this->chart('Enrollment Logs by Action', $this->grouped('registrar_enrollment_logs', 'action', $filters), 'bar'),
            $this->chart('Enrollment Logs by Person Type', $this->grouped('registrar_enrollment_logs', 'person_type', $filters), 'donut'),
            $this->chart('Enrollment Log Trend', $this->dateSeries('registrar_enrollment_logs', 'created_at', $filters), 'trend'),
        ];

        return $this->payload('registrar', 'Registrar Reports', [
            $this->card('Students', $this->countTable('student_enrollments', $filters)),
            $this->card('Active Students', $this->countTable('student_enrollments', $filters, 'created_at', fn (Builder $query) => $query->where('status', 'active'))),
            $this->card('Sections', $this->countTable('sections', $filters)),
            $this->card('Strands', $this->countTable('strands')),
            $this->card('Enrollment Logs', $this->countTable('registrar_enrollment_logs', $filters)),
        ], $charts, $filters);
    }

    // @function instructorReport: Kinukuha ang instructor report result para sa Report.
    // @useIn instructorReport: ReportController::reportPayload (app/Http/Controllers/Shared/Reports/ReportController.php)
    private function instructorReport(Request $request, array $filters): array
    {
        $instructorId = $this->instructorId((int) $request->user()->user_id);
        $scheduleIds = $this->scheduleIdsForInstructor($instructorId);
        $onlineClassIds = $this->onlineClassIdsForInstructor($instructorId);

        $scopeSchedules = fn (Builder $query) => $query->where('instructor_id', $instructorId ?: 0);
        $scopeAttendance = fn (Builder $query) => $query->whereIn('schedule_id', $scheduleIds ?: [0]);
        $scopeOnline = fn (Builder $query) => $query->where('instructor_id', $instructorId ?: 0);
        $scopeOnlineAttendance = fn (Builder $query) => $query->whereIn('online_class_id', $onlineClassIds ?: [0]);

        $charts = [
            $this->chart('Attendance by Status', $this->grouped('attendances', 'status', $filters, 'date', $scopeAttendance), 'donut'),
            $this->chart('Attendance by Subject', $this->grouped('attendances', 'subject_code', $filters, 'date', $scopeAttendance), 'bar'),
            $this->chart('Attendance Trend', $this->dateSeries('attendances', 'date', $filters, $scopeAttendance), 'trend'),
            $this->chart('Schedules by Subject', $this->grouped('schedules', 'subject_code', [], 'created_at', $scopeSchedules), 'list'),
            $this->chart('Online Classes by Status', $this->grouped('online_classes', 'status', $filters, 'scheduled_date', $scopeOnline), 'donut'),
            $this->chart('Online Class Attendance', $this->grouped('online_class_attendances', 'status', $filters, 'created_at', $scopeOnlineAttendance), 'bar'),
            $this->chart('Online Class Trend', $this->dateSeries('online_classes', 'scheduled_date', $filters, $scopeOnline), 'trend'),
        ];

        return $this->payload('instructor', 'Instructor Reports', [
            $this->card('Schedules', $this->countTable('schedules', [], 'created_at', $scopeSchedules)),
            $this->card('Attendance Records', $this->countTable('attendances', $filters, 'date', $scopeAttendance)),
            $this->card('Online Classes', $this->countTable('online_classes', $filters, 'scheduled_date', $scopeOnline)),
            $this->card('Handled Sections', $this->handledSections($instructorId)),
        ], $charts, $filters);
    }

    // @function studentReport: Kinukuha ang student report result para sa Report.
    // @useIn studentReport: ReportController::reportPayload (app/Http/Controllers/Shared/Reports/ReportController.php)
    private function studentReport(Request $request, array $filters): array
    {
        $studentIds = $this->studentIdsForUser($request);
        $scopeStudents = fn (Builder $query) => $query->whereIn('student_id', $studentIds ?: [0]);
        $scopeOnlineClasses = fn (Builder $query) => $query->whereIn(
            'section_id',
            $this->sectionIdsForStudents($studentIds, $filters['academic_year_id'] ?? null) ?: [0],
        );

        $charts = [
            $this->chart('My Attendance by Status', $this->grouped('attendances', 'status', $filters, 'date', $scopeStudents), 'donut'),
            $this->chart('My Attendance by Subject', $this->grouped('attendances', 'subject_code', $filters, 'date', $scopeStudents), 'bar'),
            $this->chart('My Attendance Trend', $this->dateSeries('attendances', 'date', $filters, $scopeStudents), 'trend'),
            $this->chart('Online Class Attendance', $this->grouped('online_class_attendances', 'status', $filters, 'created_at', $scopeStudents), 'bar'),
            $this->chart('Online Classes by Status', $this->grouped('online_classes', 'status', $filters, 'scheduled_date', $scopeOnlineClasses), 'donut'),
            $this->chart('Excuse Letters by Status', $this->grouped('student_excuse_letters', 'status', $filters, 'created_at', $scopeStudents), 'list'),
        ];

        return $this->payload('student', 'Student Reports', [
            $this->card('Attendance Records', $this->countTable('attendances', $filters, 'date', $scopeStudents)),
            $this->card('Online Class Records', $this->countTable('online_class_attendances', $filters, 'created_at', $scopeStudents)),
            $this->card('Excuse Letters', $this->countTable('student_excuse_letters', $filters, 'created_at', $scopeStudents)),
            $this->card('Messages', $this->countTable('student_portal_messages', $filters, 'created_at', $scopeStudents)),
        ], $charts, $filters);
    }

    // @function parentReport: Kinukuha ang parent report result para sa Report.
    // @useIn parentReport: ReportController::reportPayload (app/Http/Controllers/Shared/Reports/ReportController.php)
    private function parentReport(Request $request, array $filters): array
    {
        $studentIds = $this->studentIdsForUser($request);
        $scopeStudents = fn (Builder $query) => $query->whereIn('student_id', $studentIds ?: [0]);
        $scopeOnlineClasses = fn (Builder $query) => $query->whereIn(
            'section_id',
            $this->sectionIdsForStudents($studentIds, $filters['academic_year_id'] ?? null) ?: [0],
        );

        $charts = [
            $this->chart('Linked Student Attendance', $this->grouped('attendances', 'status', $filters, 'date', $scopeStudents), 'donut'),
            $this->chart('Attendance by Subject', $this->grouped('attendances', 'subject_code', $filters, 'date', $scopeStudents), 'bar'),
            $this->chart('Attendance Trend', $this->dateSeries('attendances', 'date', $filters, $scopeStudents), 'trend'),
            $this->chart('Online Class Attendance', $this->grouped('online_class_attendances', 'status', $filters, 'created_at', $scopeStudents), 'bar'),
            $this->chart('Upcoming/Created Online Classes', $this->grouped('online_classes', 'status', $filters, 'scheduled_date', $scopeOnlineClasses), 'list'),
            $this->chart('Excuse Letters by Status', $this->grouped('student_excuse_letters', 'status', $filters, 'created_at', $scopeStudents), 'donut'),
        ];

        return $this->payload('parent', 'Parent Reports', [
            $this->card('Linked Students', count($studentIds)),
            $this->card('Attendance Records', $this->countTable('attendances', $filters, 'date', $scopeStudents)),
            $this->card('Online Class Records', $this->countTable('online_class_attendances', $filters, 'created_at', $scopeStudents)),
            $this->card('Excuse Letters', $this->countTable('student_excuse_letters', $filters, 'created_at', $scopeStudents)),
        ], $charts, $filters);
    }

    // @function payload: Kinukuha ang payload result para sa Report.
    // @useIn payload: ReportController::adminReport (app/Http/Controllers/Shared/Reports/ReportController.php)
    private function payload(string $role, string $title, array $summaryCards, array $charts, array $filters): array
    {
        return [
            'role' => $role,
            'title' => $title,
            'filters' => $filters,
            'summaryCards' => $summaryCards,
            'charts' => array_values($charts),
            'tableRows' => $this->tableRows($charts),
            'exportUrl' => route('reports.export', array_filter($filters)),
            'academicYears' => AcademicYear::query()->orderByDesc('starts_on')->get(['academic_year_id', 'name', 'status', 'active_semester']),
            'selectedAcademicYear' => $filters['academic_year_id'] === 'all' ? null : AcademicYear::query()->find($filters['academic_year_id']),
            'allowAllYears' => ! in_array($role, ['student', 'parent'], true),
        ];
    }

    // @function card: Kinukuha ang card result para sa Report.
    // @useIn card: ReportController::adminReport (app/Http/Controllers/Shared/Reports/ReportController.php)
    private function card(string $label, int $value, ?string $detail = null): array
    {
        return compact('label', 'value', 'detail');
    }

    // @function chart: Kinukuha ang chart result para sa Report.
    // @useIn chart: ReportController::adminReport (app/Http/Controllers/Shared/Reports/ReportController.php)
    private function chart(string $title, array $data, string $type = 'bar'): array
    {
        return [
            'title' => $title,
            'type' => $type,
            'data' => $data,
        ];
    }

    // @function grouped: Kinukuha ang grouped result para sa Report.
    // @useIn grouped: ReportController::adminReport (app/Http/Controllers/Shared/Reports/ReportController.php)
    private function grouped(string $table, string $column, array $filters = [], string $dateColumn = 'created_at', ?Closure $scope = null): array
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return [];
        }

        $query = DB::table($table);
        $this->withoutDeleted($query, $table);
        $this->applyDateRange($query, $table, $filters, $dateColumn);
        $this->applyAcademicYear($query, $table, $filters);

        if ($scope) {
            $scope($query);
        }

        return $query
            ->selectRaw("COALESCE({$column}, 'Unspecified') as label, COUNT(*) as value")
            ->groupBy($column)
            ->orderByDesc('value')
            ->get()
            ->map(fn ($row) => [
                'label' => $this->label((string) $row->label),
                'value' => (int) $row->value,
            ])
            ->values()
            ->all();
    }

    // @function dateSeries: Kinukuha ang date series result para sa Report.
    // @useIn dateSeries: ReportController::adminReport (app/Http/Controllers/Shared/Reports/ReportController.php)
    private function dateSeries(string $table, string $dateColumn, array $filters = [], ?Closure $scope = null): array
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $dateColumn)) {
            return [];
        }

        $query = DB::table($table);
        $this->withoutDeleted($query, $table);
        $this->applyDateRange($query, $table, $filters, $dateColumn);
        $this->applyAcademicYear($query, $table, $filters);

        if ($scope) {
            $scope($query);
        }

        return $query
            ->selectRaw("DATE({$dateColumn}) as label, COUNT(*) as value")
            ->whereNotNull($dateColumn)
            ->groupByRaw("DATE({$dateColumn})")
            ->orderBy('label')
            ->limit(14)
            ->get()
            ->map(fn ($row) => [
                'label' => (string) $row->label,
                'value' => (int) $row->value,
            ])
            ->values()
            ->all();
    }

    // @function joinedStudentGroup: Kinukuha ang joined student group result para sa Report.
    // @useIn joinedStudentGroup: ReportController::registrarReport (app/Http/Controllers/Shared/Reports/ReportController.php)
    private function joinedStudentGroup(string $joinTable, string $key, string $labelColumn, array $filters = []): array
    {
        if (! Schema::hasTable('students') || ! Schema::hasTable($joinTable)) {
            return [];
        }

        $source = ! empty($filters['academic_year_id']) && $filters['academic_year_id'] !== 'all' ? 'student_enrollments' : 'students';
        $query = DB::table($source)
            ->leftJoin($joinTable, "{$source}.{$key}", '=', "{$joinTable}.{$key}")
            ->selectRaw("COALESCE({$joinTable}.{$labelColumn}, 'Unspecified') as label, COUNT(*) as value")
            ->groupBy("{$joinTable}.{$labelColumn}")
            ->orderByDesc('value');

        $this->withoutDeleted($query, $source);
        $this->applyAcademicYear($query, $source, $filters);

        return $query->get()
            ->map(fn ($row) => [
                'label' => $this->label((string) $row->label),
                'value' => (int) $row->value,
            ])
            ->values()
            ->all();
    }

    // @function countTable: Binibilang ang table sa Report flow.
    // @useIn countTable: ReportController::adminReport (app/Http/Controllers/Shared/Reports/ReportController.php)
    private function countTable(string $table, array $filters = [], string $dateColumn = 'created_at', ?Closure $scope = null): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);
        $this->withoutDeleted($query, $table);
        $this->applyDateRange($query, $table, $filters, $dateColumn);
        $this->applyAcademicYear($query, $table, $filters);

        if ($scope) {
            $scope($query);
        }

        return (int) $query->count();
    }

    // @function applyDateRange: Inilalapat ang date range sa Report flow.
    // @useIn applyDateRange: ReportController::grouped (app/Http/Controllers/Shared/Reports/ReportController.php)
    private function applyDateRange(Builder $query, string $table, array $filters, string $dateColumn): void
    {
        if (! $dateColumn || ! Schema::hasColumn($table, $dateColumn)) {
            return;
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate($dateColumn, '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate($dateColumn, '<=', $filters['date_to']);
        }
    }

    // @function applyAcademicYear: Inilalapat ang academic year sa Report flow.
    // @useIn applyAcademicYear: ReportController::grouped (app/Http/Controllers/Shared/Reports/ReportController.php)
    private function applyAcademicYear(Builder $query, string $table, array $filters): void
    {
        $yearId = $filters['academic_year_id'] ?? null;
        $semester = $filters['semester'] ?? '';

        if ($yearId && $yearId !== 'all' && Schema::hasColumn($table, 'academic_year_id')) {
            $query->where("{$table}.academic_year_id", $yearId);
        }

        if ($semester !== '' && Schema::hasColumn($table, 'semester')) {
            $query->where("{$table}.semester", $semester);
        }

        if (($yearId && $yearId !== 'all' || $semester !== '') && $table === 'students' && Schema::hasTable('student_enrollments')) {
            $query->whereExists(fn (Builder $enrollment) => $enrollment
                ->selectRaw('1')->from('student_enrollments')
                ->whereColumn('student_enrollments.student_id', 'students.student_id')
                ->when($yearId && $yearId !== 'all', fn ($q) => $q->where('student_enrollments.academic_year_id', $yearId))
                ->when($semester !== '', fn ($q) => $q->where('student_enrollments.semester', $semester)));
        }

        $needsScheduleYear = $yearId && $yearId !== 'all' && ! Schema::hasColumn($table, 'academic_year_id');
        $needsScheduleSemester = $semester !== '' && ! Schema::hasColumn($table, 'semester');

        if (($needsScheduleYear || $needsScheduleSemester) && in_array($table, ['attendances', 'online_classes'], true)) {
            $query->whereExists(fn (Builder $schedule) => $schedule
                ->selectRaw('1')->from('schedules')
                ->whereColumn('schedules.scheduled_id', "{$table}.schedule_id")
                ->when($needsScheduleYear, fn ($q) => $q->where('schedules.academic_year_id', $yearId))
                ->when($needsScheduleSemester, fn ($q) => $q->where('schedules.semester', $semester)));
        }

        if (($yearId && $yearId !== 'all' || $semester !== '') && $table === 'clinic_cases' && Schema::hasTable('student_enrollments')) {
            $query->whereExists(fn (Builder $enrollment) => $enrollment
                ->selectRaw('1')->from('student_enrollments')
                ->whereColumn('student_enrollments.student_id', 'clinic_cases.student_id')
                ->when($yearId && $yearId !== 'all', fn ($q) => $q->where('student_enrollments.academic_year_id', $yearId))
                ->when($semester !== '', fn ($q) => $q->where('student_enrollments.semester', $semester)));
        }
    }

    // @function resolvedAcademicYearId: Kinukuha ang resolved academic year id result para sa Report.
    // @useIn resolvedAcademicYearId: ReportController::reportPayload (app/Http/Controllers/Shared/Reports/ReportController.php)
    private function resolvedAcademicYearId(Request $request, string $role): int|string|null
    {
        $requested = $request->input('academic_year_id');
        if ($requested === 'all' && ! in_array($role, ['student', 'parent'], true)) {
            return 'all';
        }

        $yearId = is_numeric($requested) ? (int) $requested : AcademicYear::currentOrLatest()?->academic_year_id;
        if (! $yearId) {
            return 'all';
        }
        abort_unless(AcademicYear::query()->whereKey($yearId)->exists(), 404);

        if (in_array($role, ['student', 'parent'], true)) {
            $studentIds = $this->studentIdsForUser($request);
            abort_unless(DB::table('student_enrollments')->whereIn('student_id', $studentIds ?: [0])->where('academic_year_id', $yearId)->exists(), 403);
        }

        return $yearId;
    }

    // @function withoutDeleted: Pinoproseso ang without deleted para sa Report.
    // @useIn withoutDeleted: ReportController::grouped (app/Http/Controllers/Shared/Reports/ReportController.php)
    private function withoutDeleted(Builder $query, string $table): void
    {
        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull("{$table}.deleted_at");
        }
    }

    // @function tableRows: Binubuo ang table rows value.
    // @useIn tableRows: ReportController::payload (app/Http/Controllers/Shared/Reports/ReportController.php)
    private function tableRows(array $charts): array
    {
        $rows = [];

        foreach ($charts as $chart) {
            foreach ($chart['data'] as $datum) {
                $rows[] = [
                    'category' => $chart['title'],
                    'metric' => $datum['label'],
                    'value' => $datum['value'],
                    'group' => 'Count',
                ];
            }
        }

        return $rows;
    }

    // @function instructorId: Kinukuha ang instructor id result para sa Report.
    // @useIn instructorId: ReportController::instructorReport (app/Http/Controllers/Shared/Reports/ReportController.php)
    private function instructorId(int $userId): ?int
    {
        if (! Schema::hasTable('instructors')) {
            return null;
        }

        return DB::table('instructors')->whereNull('instructors.deleted_at')->where('user_id', $userId)->value('instructor_id');
    }

    // @function scheduleIdsForInstructor: Kinukuha ang schedule ids for instructor result para sa Report.
    // @useIn scheduleIdsForInstructor: ReportController::instructorReport (app/Http/Controllers/Shared/Reports/ReportController.php)
    private function scheduleIdsForInstructor(?int $instructorId): array
    {
        if (! $instructorId || ! Schema::hasTable('schedules') || ! Schema::hasColumn('schedules', 'instructor_id')) {
            return [];
        }

        return DB::table('schedules')
            ->where('instructor_id', $instructorId)
            ->pluck('scheduled_id')
            ->all();
    }

    // @function handledSections: Kinukuha ang handled sections result para sa Report.
    // @useIn handledSections: ReportController::instructorReport (app/Http/Controllers/Shared/Reports/ReportController.php)
    private function handledSections(?int $instructorId): int
    {
        if (! $instructorId || ! Schema::hasTable('schedules') || ! Schema::hasColumn('schedules', 'instructor_id')) {
            return 0;
        }

        return DB::table('schedules')
            ->where('instructor_id', $instructorId)
            ->distinct()
            ->count('section_id');
    }

    // @function onlineClassIdsForInstructor: Kinukuha ang online class ids for instructor result para sa Report.
    // @useIn onlineClassIdsForInstructor: ReportController::instructorReport (app/Http/Controllers/Shared/Reports/ReportController.php)
    private function onlineClassIdsForInstructor(?int $instructorId): array
    {
        if (! $instructorId || ! Schema::hasTable('online_classes') || ! Schema::hasColumn('online_classes', 'instructor_id')) {
            return [];
        }

        return DB::table('online_classes')
            ->whereNull('online_classes.deleted_at')
            ->where('instructor_id', $instructorId)
            ->pluck('online_class_id')
            ->all();
    }

    // @function studentIdsForUser: Kinukuha ang student ids for user result para sa Report.
    // @useIn studentIdsForUser: ReportController::studentReport (app/Http/Controllers/Shared/Reports/ReportController.php)
    private function studentIdsForUser(Request $request): array
    {
        $user = $request->user();
        $role = strtolower((string) $user?->role);

        if ($role === 'parent' && Schema::hasTable('parent_student_links')) {
            return DB::table('parent_student_links')
                ->join('students', 'students.student_id', '=', 'parent_student_links.student_id')
                ->whereNull('students.deleted_at')
                ->where('parent_user_id', $user?->user_id ?: 0)
                ->pluck('parent_student_links.student_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        if ($role !== 'student' || ! Schema::hasTable('students')) {
            return [];
        }

        $query = DB::table('students')->whereNull('students.deleted_at');

        if (Schema::hasColumn('students', 'email') && $user?->email) {
            $query->where('email', $user->email);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    // @function sectionIdsForStudents: Kinukuha ang section ids for students result para sa Report.
    // @useIn sectionIdsForStudents: ReportController::studentReport (app/Http/Controllers/Shared/Reports/ReportController.php)
    private function sectionIdsForStudents(array $studentIds, int|string|null $academicYearId = null): array
    {
        if (! $studentIds || ! Schema::hasTable('students') || ! Schema::hasColumn('students', 'section_id')) {
            return [];
        }

        $table = $academicYearId && $academicYearId !== 'all' ? 'student_enrollments' : 'students';

        return DB::table($table)
            ->whereIn('student_id', $studentIds)
            ->when($table === 'student_enrollments', fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->whereNotNull('section_id')
            ->distinct()
            ->pluck('section_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    // @function label: Binubuo ang label string para sa Report.
    // @useIn label: ReportController::grouped (app/Http/Controllers/Shared/Reports/ReportController.php)
    private function label(string $value): string
    {
        return trim(ucwords(str_replace(['_', '-'], ' ', $value))) ?: 'Unspecified';
    }
}
