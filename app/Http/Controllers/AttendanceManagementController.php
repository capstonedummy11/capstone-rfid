<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Instructor;
use App\Models\OnlineClassAttendance;
use App\Models\Students;
use App\Models\Subject;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AttendanceManagementController extends Controller
{
    private const CORE_STATUSES = [
        'Present',
        'Absent',
        'Late',
        'Excused',
        'Unexcused',
        'Online Class',
    ];

    public function index(Request $request): Response|SymfonyResponse
    {
        [$role, $instructorId] = $this->actor($request);
        $subjects = $this->subjectsFor($role, $instructorId, $request)->get();

        if ($role === 'instructor' && $subjects->count() === 1) {
            return redirect()->route('admin.attendance.subject', $subjects->first()->subject_id);
        }

        return Inertia::render('Attendance/SubjectSelection', [
            'subjects' => $subjects->map(fn (Subject $subject) => $this->subjectCard($subject))->values(),
            'filters' => $request->only(['school_year', 'semester', 'department', 'course', 'section', 'instructor']),
            'filterOptions' => $role === 'admin' ? $this->adminFilterOptions() : null,
            'currentUserRole' => $role,
        ]);
    }

    public function dashboard(Request $request, Subject $subject): Response
    {
        $context = $this->authorizeSubject($request, $subject);
        $sessions = $this->sessionsFor($subject, $context['instructor_id'])->get();
        $students = $this->studentsFor($subject)->get();
        $summary = $this->summaryRows($subject, $sessions, $students);
        $statusTotals = $this->statusTotals($summary);

        return Inertia::render('Attendance/Dashboard', [
            'subject' => $this->subjectMeta($subject, $context['instructor_id']),
            'overview' => [
                'total_students' => $students->count(),
                'total_sessions' => $sessions->count(),
                'statuses' => $statusTotals,
            ],
            'sessions' => $sessions->map(fn ($session) => $this->sessionCard($session, $students->count()))->values(),
            'currentUserRole' => $context['role'],
        ]);
    }

    public function summary(Request $request, Subject $subject): Response
    {
        $context = $this->authorizeSubject($request, $subject);
        $sessions = $this->sessionsFor($subject, $context['instructor_id'])->get();
        $rows = $this->summaryRows($subject, $sessions, $this->studentsFor($subject)->get());

        return Inertia::render('Attendance/Summary', [
            'subject' => $this->subjectMeta($subject, $context['instructor_id']),
            'statuses' => $this->statusNames($rows),
            'rows' => $rows->values(),
            'totalSessions' => $sessions->count(),
        ]);
    }

    public function student(Request $request, Subject $subject, Students $student): Response
    {
        $context = $this->authorizeSubject($request, $subject);
        abort_unless((int) $student->section_id === (int) $subject->section_id, 404);
        $sessions = $this->sessionsFor($subject, $context['instructor_id'])->get();
        $history = $sessions->map(function ($session) use ($student) {
            $attendance = Attendance::query()
                ->where('schedule_id', $session->schedule_id)
                ->where('student_id', $student->student_id)
                ->whereDate('date', $session->date)
                ->first();

            return [
                'session_id' => $session->attendance_id,
                'date' => Carbon::parse($session->date)->toDateString(),
                'date_label' => Carbon::parse($session->date)->format('F j, Y'),
                'schedule' => $this->timeRange($session->time_start, $session->time_end),
                'room' => $session->room,
                'status' => $attendance ? $this->displayStatus($attendance, $session) : $this->missingStatus($session),
                'time_in' => $this->formatTime($attendance?->time_in),
                'time_out' => $this->formatTime($attendance?->time_out),
                'remarks' => $attendance?->remarks,
            ];
        });

        return Inertia::render('Attendance/StudentHistory', [
            'subject' => $this->subjectMeta($subject, $context['instructor_id']),
            'student' => [
                'id' => $student->student_id,
                'name' => trim($student->first_name.' '.$student->last_name),
                'student_number' => $student->student_number,
            ],
            'history' => $history->values(),
        ]);
    }

    public function session(Request $request, Subject $subject, int $session): Response
    {
        $context = $this->authorizeSubject($request, $subject);
        $attendanceSession = $this->sessionForSubject($subject, $session, $context['instructor_id']);
        $rows = $this->sessionRows($subject, $attendanceSession);

        return Inertia::render('Attendance/SessionDetails', [
            'subject' => $this->subjectMeta($subject, $context['instructor_id']),
            'session' => $this->sessionMeta($attendanceSession),
            'statuses' => $rows->pluck('status')->unique()->sort()->values(),
            'statusTotals' => $rows->countBy('status')->sortKeys(),
            'rows' => $rows->values(),
            'canEditAttendance' => in_array($context['role'], ['admin', 'instructor'], true),
            'absentDefaultDays' => \App\Models\SystemSetting::integer(\App\Models\SystemSetting::ATTENDANCE_ABSENT_DEFAULT_DAYS, 15),
        ]);
    }

    public function exportSummary(Request $request, Subject $subject, string $format): SymfonyResponse
    {
        $context = $this->authorizeSubject($request, $subject);
        $sessions = $this->sessionsFor($subject, $context['instructor_id'])->get();
        $rows = $this->summaryRows($subject, $sessions, $this->studentsFor($subject)->get());
        $statuses = $this->statusNames($rows);
        $meta = $this->reportMeta($request, $subject, $context['instructor_id'], 'Student Attendance Summary', [
            'Total Students' => $rows->count(),
            'Total Attendance Sessions' => $sessions->count(),
        ]);
        $headings = collect(['Student Number', 'Student Name'])->merge($statuses)->push('Attendance Rate (%)')->all();
        $data = $rows->map(function ($row) use ($statuses) {
            return collect([$row['student_number'], $row['student_name']])
                ->merge($statuses->map(fn ($status) => $row['counts'][$status] ?? 0))
                ->push($row['attendance_rate'])
                ->all();
        });

        return $this->export($format, 'student-attendance-summary', $meta, $headings, $data, $this->statusTotals($rows));
    }

    public function exportSession(Request $request, Subject $subject, int $session, string $format): SymfonyResponse
    {
        $context = $this->authorizeSubject($request, $subject);
        $attendanceSession = $this->sessionForSubject($subject, $session, $context['instructor_id']);
        $rows = $this->sessionRows($subject, $attendanceSession);
        $meta = $this->reportMeta($request, $subject, $context['instructor_id'], 'Attendance Sheet', [
            'Attendance Date' => Carbon::parse($attendanceSession->date)->format('F j, Y'),
            'Class Schedule' => $this->timeRange($attendanceSession->time_start, $attendanceSession->time_end),
        ]);
        $data = $rows->map(fn ($row) => [
            $row['student_number'],
            $row['student_name'],
            $row['status'],
            $row['time_in'] ?? '',
            $row['time_out'] ?? '',
            $row['remarks'] ?? '',
        ]);

        return $this->export(
            $format,
            'attendance-sheet-'.Carbon::parse($attendanceSession->date)->format('Y-m-d'),
            $meta,
            ['Student Number', 'Student Name', 'Status', 'Time In', 'Time Out', 'Remarks'],
            $data,
            $rows->countBy('status')->sortKeys()
        );
    }

    private function actor(Request $request): array
    {
        $role = strtolower((string) $request->user()?->role);
        abort_unless(in_array($role, ['admin', 'instructor'], true), 403);
        $instructorId = $role === 'instructor'
            ? Instructor::query()->where('user_id', $request->user()->user_id)->value('instructor_id')
            : null;
        abort_if($role === 'instructor' && ! $instructorId, 403, 'No instructor profile is linked to this account.');

        return [$role, $instructorId ? (int) $instructorId : null];
    }

    private function authorizeSubject(Request $request, Subject $subject): array
    {
        [$role, $instructorId] = $this->actor($request);
        if ($role === 'instructor') {
            abort_unless($this->subjectAssignedTo($subject, $instructorId), 403);
        }
        $subject->loadMissing('section.strand');

        return compact('role', 'instructorId') + ['instructor_id' => $instructorId];
    }

    private function subjectAssignedTo(Subject $subject, int $instructorId): bool
    {
        return DB::table('schedules')
            ->where('instructor_id', $instructorId)
            ->where('section_id', $subject->section_id)
            ->where('subject_code', $subject->subject_code)
            ->exists();
    }

    private function subjectsFor(string $role, ?int $instructorId, Request $request): Builder
    {
        return Subject::query()
            ->with(['section.strand'])
            ->whereHas('schedules', function (Builder $query) use ($role, $instructorId, $request) {
                $query->whereColumn('schedules.section_id', 'subjects.section_id');
                if ($role === 'instructor') {
                    $query->where('schedules.instructor_id', $instructorId);
                } elseif ($request->filled('instructor')) {
                    $query->where('schedules.instructor_id', $request->integer('instructor'));
                }
            })
            ->when($request->filled('school_year'), fn ($q) => $q->whereHas('section', fn ($s) => $s->where('school_year', $request->input('school_year'))))
            ->when($request->filled('semester'), fn ($q) => $q->where(function ($s) use ($request) {
                $s->where('semester', $request->input('semester'))
                    ->orWhereHas('section', fn ($section) => $section->where('semester', $request->input('semester')));
            }))
            ->when($request->filled('department'), fn ($q) => $q->where('department', $request->input('department')))
            ->when($request->filled('course'), fn ($q) => $q->whereHas('section', fn ($section) => $section->where('strand_id', $request->integer('course'))))
            ->when($request->filled('section'), fn ($q) => $q->where('section_id', $request->integer('section')))
            ->orderBy('subject_name');
    }

    private function sessionsFor(Subject $subject, ?int $instructorId)
    {
        return DB::table('attendance_sessions')
            ->join('schedules', 'schedules.scheduled_id', '=', 'attendance_sessions.schedule_id')
            ->where('schedules.section_id', $subject->section_id)
            ->where('schedules.subject_code', $subject->subject_code)
            ->when($instructorId, fn ($query) => $query->where('schedules.instructor_id', $instructorId))
            ->select('attendance_sessions.*', 'schedules.instructor_id', 'schedules.section_id')
            ->orderByDesc('attendance_sessions.date')
            ->orderByDesc('attendance_sessions.time_start');
    }

    private function studentsFor(Subject $subject): Builder
    {
        return Students::query()
            ->where('section_id', $subject->section_id)
            ->where(function ($query) {
                $query->whereNull('status')->orWhereRaw('LOWER(status) = ?', ['active']);
            })
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    private function summaryRows(Subject $subject, Collection $sessions, Collection $students): Collection
    {
        $physical = Attendance::query()
            ->whereIn('schedule_id', $sessions->pluck('schedule_id')->filter()->unique())
            ->whereIn('student_id', $students->pluck('student_id'))
            ->get()
            ->keyBy(fn (Attendance $attendance) => $attendance->student_id.'|'.$attendance->schedule_id.'|'.$attendance->date?->toDateString());

        $onlineCounts = OnlineClassAttendance::query()
            ->whereIn('student_id', $students->pluck('student_id'))
            ->whereHas('onlineClass', fn ($query) => $query
                ->where('section_id', $subject->section_id)
                ->where('subject_code', $subject->subject_code))
            ->where(function ($query) {
                $query->whereNotNull('joined_at')
                    ->orWhereIn(DB::raw('LOWER(status)'), ['joined', 'attended', 'present', 'late']);
            })
            ->select('student_id', DB::raw('COUNT(*) as total'))
            ->groupBy('student_id')
            ->pluck('total', 'student_id');
        $onlineSessionCount = DB::table('online_classes')
            ->where('section_id', $subject->section_id)
            ->where('subject_code', $subject->subject_code)
            ->whereNull('deleted_at')
            ->count();

        return $students->map(function (Students $student) use ($sessions, $physical, $onlineCounts, $onlineSessionCount) {
            $counts = collect();
            foreach ($sessions as $session) {
                $key = $student->student_id.'|'.$session->schedule_id.'|'.Carbon::parse($session->date)->toDateString();
                $attendance = $physical->get($key);
                $status = $attendance ? $this->displayStatus($attendance, $session) : $this->missingStatus($session);
                $counts[$status] = ($counts[$status] ?? 0) + 1;
            }
            if (($onlineCounts[$student->student_id] ?? 0) > 0) {
                $onlinePresent = (int) $onlineCounts[$student->student_id];
                $counts['Online Class'] = $onlinePresent;
                $counts['Present'] = ($counts['Present'] ?? 0) + $onlinePresent;
            }
            $completed = (int) ($counts['Present'] ?? 0) + (int) ($counts['Late'] ?? 0) + (int) ($counts['Excused'] ?? 0);
            $rateBase = max(1, $sessions->count() + $onlineSessionCount);

            return [
                'student_id' => $student->student_id,
                'student_number' => $student->student_number,
                'student_name' => trim($student->first_name.' '.$student->last_name),
                'counts' => $counts->sortKeys()->all(),
                'attendance_rate' => round(($completed / $rateBase) * 100, 1),
            ];
        });
    }

    private function sessionRows(Subject $subject, object $session): Collection
    {
        $attendances = Attendance::query()
            ->where('schedule_id', $session->schedule_id)
            ->whereDate('date', $session->date)
            ->get()
            ->keyBy('student_id');

        return $this->studentsFor($subject)->get()->map(function (Students $student) use ($attendances, $session) {
            $attendance = $attendances->get($student->student_id);

            return [
                'student_id' => $student->student_id,
                'student_number' => $student->student_number,
                'student_name' => trim($student->first_name.' '.$student->last_name),
                'status' => $attendance ? $this->displayStatus($attendance, $session) : $this->missingStatus($session),
                'time_in' => $this->formatTime($attendance?->time_in),
                'time_out' => $this->formatTime($attendance?->time_out),
                'remarks' => $attendance?->remarks,
                'editable' => Carbon::parse($session->date)->betweenIncluded(
                    now()->subDays(max(1, \App\Models\SystemSetting::integer(\App\Models\SystemSetting::ATTENDANCE_ABSENT_DEFAULT_DAYS, 15)) - 1)->startOfDay(),
                    now()->endOfDay()
                ),
            ];
        });
    }

    private function sessionForSubject(Subject $subject, int $session, ?int $instructorId): object
    {
        $record = $this->sessionsFor($subject, $instructorId)
            ->where('attendance_sessions.attendance_id', $session)
            ->first();
        abort_unless($record, 404);

        return $record;
    }

    private function displayStatus(Attendance $attendance, object $session): string
    {
        $status = trim((string) $attendance->status);
        if (strcasecmp($status, 'pending') === 0 && $this->sessionEnded($session)) {
            return empty($attendance->time_out) ? 'Incomplete Attendance' : ucfirst((string) ($attendance->check_in_status ?: 'present'));
        }

        return $status === '' ? 'Pending' : ucwords(str_replace('_', ' ', strtolower($status)));
    }

    private function missingStatus(object $session): string
    {
        return $this->sessionEnded($session) ? 'Absent' : 'Pending';
    }

    private function sessionEnded(object $session): bool
    {
        return Carbon::parse($session->date.' '.$session->time_end)->isPast()
            || in_array(strtolower((string) ($session->status ?? '')), ['completed', 'ended', 'closed'], true);
    }

    private function statusNames(Collection $rows): Collection
    {
        $dynamicStatuses = $rows
            ->flatMap(fn ($row) => array_keys($row['counts']))
            ->diff(self::CORE_STATUSES)
            ->unique()
            ->sort()
            ->values();

        return collect(self::CORE_STATUSES)->merge($dynamicStatuses);
    }

    private function statusTotals(Collection $rows): array
    {
        $totals = collect(self::CORE_STATUSES)->mapWithKeys(fn ($status) => [$status => 0]);
        foreach ($rows as $row) {
            foreach ($row['counts'] as $status => $count) {
                $totals[$status] = ($totals[$status] ?? 0) + $count;
            }
        }

        return $totals->sortKeys()->all();
    }

    private function subjectCard(Subject $subject): array
    {
        $instructors = $subject->schedules()
            ->where('section_id', $subject->section_id)
            ->with('instructor.user')
            ->get()
            ->pluck('instructor.user.name')
            ->filter()
            ->unique()
            ->values();

        return $this->subjectMeta($subject, null) + ['instructors' => $instructors];
    }

    private function subjectMeta(Subject $subject, ?int $instructorId): array
    {
        $subject->loadMissing('section.strand');
        $instructorNames = $subject->schedules()
            ->where('section_id', $subject->section_id)
            ->when($instructorId, fn ($query) => $query->where('instructor_id', $instructorId))
            ->with('instructor.user')
            ->get()
            ->pluck('instructor.user.name')
            ->filter()
            ->unique()
            ->implode(', ');

        return [
            'id' => $subject->subject_id,
            'code' => $subject->subject_code,
            'name' => $subject->subject_name,
            'department' => $subject->department,
            'semester' => $subject->semester ?: $subject->section?->semester,
            'section_id' => $subject->section_id,
            'section' => $subject->section?->section_name,
            'school_year' => $subject->section?->school_year,
            'strand' => $subject->section?->strand?->strand_code,
            'instructor' => $instructorNames ?: 'Unassigned Instructor',
        ];
    }

    private function sessionCard(object $session, int $studentCount): array
    {
        $completed = Attendance::query()
            ->where('schedule_id', $session->schedule_id)
            ->whereDate('date', $session->date)
            ->whereNotIn(DB::raw('LOWER(status)'), ['pending'])
            ->count();

        return $this->sessionMeta($session) + [
            'total_students' => $studentCount,
            'completion' => $studentCount > 0 ? round(($completed / $studentCount) * 100) : 0,
        ];
    }

    private function sessionMeta(object $session): array
    {
        return [
            'id' => $session->attendance_id,
            'date' => Carbon::parse($session->date)->toDateString(),
            'date_label' => Carbon::parse($session->date)->format('F j, Y'),
            'schedule' => $this->timeRange($session->time_start, $session->time_end),
            'room' => $session->room,
            'status' => ucfirst((string) $session->status),
        ];
    }

    private function adminFilterOptions(): array
    {
        return [
            'schoolYears' => DB::table('sections')->whereNotNull('school_year')->distinct()->orderByDesc('school_year')->pluck('school_year'),
            'semesters' => DB::table('sections')->whereNotNull('semester')->distinct()->orderBy('semester')->pluck('semester'),
            'departments' => DB::table('subjects')->whereNotNull('department')->where('department', '!=', '')->distinct()->orderBy('department')->pluck('department'),
            'courses' => DB::table('strands')
                ->orderBy('strand_name')
                ->get(['strand_id as value', 'strand_code', 'strand_name'])
                ->map(fn ($strand) => [
                    'value' => $strand->value,
                    'label' => trim(implode(' - ', array_filter([$strand->strand_code, $strand->strand_name]))),
                ]),
            'sections' => DB::table('sections')->orderBy('section_name')->get(['section_id as value', 'section_name as label']),
            'instructors' => DB::table('instructors')->join('users', 'users.user_id', '=', 'instructors.user_id')->orderBy('users.name')->get(['instructors.instructor_id as value', 'users.name as label']),
        ];
    }

    private function reportMeta(Request $request, Subject $subject, ?int $instructorId, string $title, array $extra): array
    {
        $meta = $this->subjectMeta($subject, $instructorId);

        return [
            '__logo' => resource_path('js/assets/images/logo-only.jpg'),
            'School Name' => config('app.school_name', 'Pasay City South High School'),
            'Report Title' => $title,
            'Subject' => $meta['code'].' - '.$meta['name'],
            'Section' => $meta['section'] ?: 'N/A',
            'Instructor' => $meta['instructor'],
            'School Year' => $meta['school_year'] ?: 'N/A',
            'Semester / Term' => $meta['semester'] ?: 'N/A',
        ] + $extra + [
            'Date Generated' => now()->format('F j, Y g:i A'),
            'Generated By' => $request->user()->name,
        ];
    }

    private function export(string $format, string $filename, array $meta, array $headings, Collection $rows, array|Collection $totals): SymfonyResponse
    {
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);
        $totals = collect($totals)->all();
        if ($format === 'pdf') {
            return Pdf::loadView('reports.attendance', compact('meta', 'headings', 'rows', 'totals'))
                ->setPaper('a4', count($headings) > 7 ? 'landscape' : 'portrait')
                ->download($filename.'.pdf');
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Attendance Report');
        $rowNumber = 1;
        foreach ($meta as $label => $value) {
            if ($label === '__logo') {
                continue;
            }
            $sheet->setCellValue("A{$rowNumber}", $label);
            $sheet->setCellValue("B{$rowNumber}", $value);
            $sheet->getStyle("A{$rowNumber}")->getFont()->setBold(true);
            $rowNumber++;
        }
        if (is_file($meta['__logo'] ?? null)) {
            $drawing = new Drawing;
            $drawing->setName('School Logo');
            $drawing->setPath($meta['__logo']);
            $drawing->setHeight(58);
            $drawing->setCoordinates('D1');
            $drawing->setWorksheet($sheet);
        }
        $rowNumber++;
        $lastColumn = Coordinate::stringFromColumnIndex(count($headings));
        $sheet->fromArray($headings, null, "A{$rowNumber}");
        $sheet->getStyle("A{$rowNumber}:{$lastColumn}{$rowNumber}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $rowNumber++;
        foreach ($rows as $row) {
            $sheet->fromArray($row, null, "A{$rowNumber}");
            $rowNumber++;
        }
        $rowNumber++;
        $sheet->setCellValue("A{$rowNumber}", 'Status Totals');
        $sheet->getStyle("A{$rowNumber}")->getFont()->setBold(true);
        foreach ($totals as $status => $count) {
            $rowNumber++;
            $sheet->setCellValue("A{$rowNumber}", $status);
            $sheet->setCellValue("B{$rowNumber}", $count);
        }
        foreach (range(1, count($headings)) as $column) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }

        $path = tempnam(sys_get_temp_dir(), 'attendance-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, $filename.'.xlsx')->deleteFileAfterSend(true);
    }

    private function timeRange(?string $start, ?string $end): string
    {
        return ($this->formatTime($start) ?: 'N/A').' - '.($this->formatTime($end) ?: 'N/A');
    }

    private function formatTime(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->format('g:i A') : null;
    }
}
