<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Instructor;
use App\Models\Message;
use App\Models\OnlineClass;
use App\Models\OnlineClassNotification;
use App\Models\Section;
use App\Models\Strand;
use App\Models\StudentExcuseLetter;
use App\Models\StudentPortalMessage;
use App\Models\Students;
use App\Services\CompreFaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class StudentsController
{
    public function index()
    {
        //
    }

    public function indexAdmin(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'strand' => trim((string) $request->input('strand', '')),
            'section' => trim((string) $request->input('section', '')),
            'year' => trim((string) $request->input('year', '')),
            'school_year' => trim((string) $request->input('school_year', '')),
            'status' => trim((string) $request->input('status', '')),
        ];

        $user = $request->user();
        $role = strtolower(trim((string) $user?->role));
        $isAdmin = $role === 'admin';
        $isInstructor = $role === 'instructor';
        $handledSectionIds = collect();

        if ($isInstructor) {
            $instructorId = Instructor::query()
                ->where('user_id', $user?->user_id)
                ->value('instructor_id');

            $handledSectionIds = Section::query()
                ->whereHas('schedules', fn ($scheduleQuery) => $scheduleQuery->where('instructor_id', $instructorId ?: 0))
                ->pluck('section_id');
        }

        $query = Students::query()->with(['section', 'strand']);

        if ($isInstructor) {
            $query->whereIn('section_id', $handledSectionIds->all());
        }

        if ($filters['search'] !== '') {
            $term = strtolower($filters['search']);
            $query->where(function ($studentQuery) use ($term) {
                $studentQuery
                    ->whereRaw('LOWER(first_name) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(student_number) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(rfid_tag) LIKE ?', ["%{$term}%"]);
            });
        }

        if ($filters['strand'] !== '') {
            $query->where('strand_id', $filters['strand']);
        }

        if ($filters['section'] !== '') {
            if ($isInstructor && ! $handledSectionIds->contains((int) $filters['section'])) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('section_id', $filters['section']);
            }
        }

        if ($filters['year'] !== '') {
            $query->where('year_level', $filters['year']);
        }

        if ($filters['school_year'] !== '') {
            $query->where('school_year', $filters['school_year']);
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        $students = $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function (Students $student) {
                return [
                    'student_id' => $student->student_id,
                    'student_number' => $student->student_number,
                    'first_name' => $student->first_name,
                    'middle_name' => $student->middle_name,
                    'last_name' => $student->last_name,
                    'email' => $student->email,
                    'phone' => $student->phone,
                    'gender' => $student->gender,
                    'strand_id' => $student->strand_id,
                    'strand_code' => $student->strand?->strand_code,
                    'section_id' => $student->section_id,
                    'section_name' => $student->section?->section_name,
                    'year_level' => $student->year_level,
                    'semester' => $student->semester,
                    'school_year' => $student->school_year,
                    'rfid_tag' => $student->rfid_tag,
                    'face_images' => $student->face_images ?? [],
                    'status' => $student->status ?? 'active',
                ];
            })
            ->values();

        return Inertia::render('Auth/Admin/Students', [
            'students' => $students,
            'filters' => $filters,
            'currentUserRole' => $role,
            'canManageStudents' => $isAdmin,
            'strandOptions' => Strand::query()
                ->orderBy('strand_code')
                ->get(['strand_id', 'strand_code', 'strand_name'])
                ->map(fn (Strand $strand) => [
                    'strand_id' => $strand->strand_id,
                    'strand_code' => $strand->strand_code,
                    'strand_name' => $strand->strand_name,
                ])
                ->values(),
            'sectionOptions' => Section::query()
                ->with(['strand'])
                ->when($isInstructor, fn ($sectionQuery) => $sectionQuery->whereIn('section_id', $handledSectionIds->all()))
                ->orderBy('section_name')
                ->get()
                ->map(fn (Section $section) => [
                    'section_id' => $section->section_id,
                    'section_name' => $section->section_name,
                    'strand_id' => $section->strand_id,
                    'school_year' => $section->school_year,
                    'label' => trim(implode(' - ', array_filter([
                        $section->section_name,
                        $section->strand?->strand_code,
                        $section->school_year,
                    ]))),
                ])
                ->values(),
            'schoolYearOptions' => Section::query()
                ->when($isInstructor, fn ($sectionQuery) => $sectionQuery->whereIn('section_id', $handledSectionIds->all()))
                ->whereNotNull('school_year')
                ->distinct()
                ->orderByDesc('school_year')
                ->pluck('school_year')
                ->values(),
        ]);
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_number' => 'required|string|max:255|unique:students,student_number',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:students,email',
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|in:male,female',
            'strand_id' => 'required|exists:strands,strand_id',
            'section_id' => 'required|exists:sections,section_id',
            'year_level' => 'required|integer|in:11,12',
            'semester' => 'required|string|max:50',
            'school_year' => 'required|string|max:20',
            'rfid_tag' => 'nullable|string|max:255|unique:students,rfid_tag',
            'status' => 'required|in:active,inactive,graduated,dropped',
        ]);

        $student = Students::create($validated);
        $this->logActivity('create', 'students', 'Created student '.$student->student_number);

        return back()->with('success', 'Student added successfully.');
    }

    public function show(Students $students)
    {
        //
    }

    public function edit(Students $students)
    {
        //
    }

    public function update(Request $request, $id)
    {
        $student = Students::findOrFail($id);

        $validated = $request->validate([
            'student_number' => 'required|string|max:255|unique:students,student_number,'.$id.',student_id',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:students,email,'.$id.',student_id',
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|in:male,female',
            'strand_id' => 'required|exists:strands,strand_id',
            'section_id' => 'required|exists:sections,section_id',
            'year_level' => 'required|integer|in:11,12',
            'semester' => 'required|string|max:50',
            'school_year' => 'required|string|max:20',
            'rfid_tag' => 'nullable|string|max:255|unique:students,rfid_tag,'.$id.',student_id',
            'status' => 'required|in:active,inactive,graduated,dropped',
        ]);

        $student->update($validated);
        $this->logActivity('update', 'students', 'Updated student '.$student->student_number);

        return back()->with('success', 'Student updated successfully.');
    }

    public function uploadFaceImage(Request $request, $id)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:4096',
        ]);

        $student = Students::findOrFail($id);
        $currentImages = $student->face_images ?? [];

        if (count($currentImages) >= 5) {
            return response()->json(['ok' => false, 'message' => 'Maximum 5 face images allowed per student.'], 422);
        }

        $path = $request->file('image')->store('student_faces', 'public');
        $currentImages[] = $path;
        $student->update(['face_images' => $currentImages]);

        // Enroll this face into CompreFace (student_number is the subject)
        $absolutePath = Storage::disk('public')->path($path);
        (new CompreFaceService)->enrollFace($student->student_number, $absolutePath);

        $this->logActivity('update', 'students', 'Added face image for student '.$student->student_number);

        return response()->json([
            'ok' => true,
            'message' => 'Face image uploaded successfully.',
            'face_images' => $student->fresh()->face_images ?? [],
        ]);
    }

    public function deleteFaceImage($id, $index)
    {
        $student = Students::findOrFail($id);
        $currentImages = $student->face_images ?? [];

        if (! isset($currentImages[(int) $index])) {
            return response()->json(['ok' => false, 'message' => 'Image not found.'], 404);
        }

        Storage::disk('public')->delete($currentImages[(int) $index]);
        array_splice($currentImages, (int) $index, 1);
        $student->update(['face_images' => array_values($currentImages)]);

        $this->logActivity('update', 'students', 'Removed face image for student '.$student->student_number);

        return response()->json([
            'ok' => true,
            'message' => 'Face image removed successfully.',
            'face_images' => $student->fresh()->face_images ?? [],
        ]);
    }

    public function destroy($id)
    {
        $student = Students::findOrFail($id);
        $studentNumber = $student->student_number;

        // Remove all stored face image files and CompreFace subject
        foreach ($student->face_images ?? [] as $imagePath) {
            Storage::disk('public')->delete($imagePath);
        }
        (new CompreFaceService)->deleteSubject($student->student_number);

        $student->delete();

        $this->logActivity('delete', 'students', 'Deleted student '.$studentNumber);

        return back()->with('success', 'Student deleted successfully.');
    }

    public function portalDashboard(Request $request)
    {
        $student = $this->currentStudent($request);

        return Inertia::render('StudentParent/Dashboard', [
            'title' => 'Student Dashboard',
            'student' => $this->studentPayload($student),
            'linkedStudents' => $this->linkedStudentsPayload($request),
            'selectedStudentId' => $student?->student_id,
            'stats' => [
                'present' => $student?->attendances()->where('status', 'present')->count() ?? 0,
                'late' => $student?->attendances()->where('status', 'late')->count() ?? 0,
                'excuse_letters' => $student?->excuseLetters()->count() ?? 0,
                'messages' => $student ? $this->messageQuery($request, $student)->count() : 0,
                'online_classes' => $student
                    ? OnlineClass::query()->where('section_id', $student->section_id)->where('status', 'scheduled')->count()
                    : 0,
            ],
            'recentAttendance' => $this->attendanceQuery($student)->take(5)->get()->map(fn ($attendance) => $this->attendancePayload($attendance)),
            'attendance' => $this->attendanceQuery($student)->take(100)->get()->map(fn ($attendance) => $this->attendancePayload($attendance)),
            'recentMessages' => $student ? $this->messageQuery($request, $student)->take(5)->get()->map(fn ($message) => $this->messagePayload($message)) : [],
        ]);
    }

    public function portalProfile(Request $request)
    {
        return Inertia::render('StudentParent/Profile', [
            'title' => 'My Profile',
            'student' => $this->studentPayload($this->currentStudent($request)),
            'linkedStudents' => $this->linkedStudentsPayload($request),
            'selectedStudentId' => $this->currentStudent($request)?->student_id,
            'user' => $request->user(),
        ]);
    }

    public function updatePortalProfile(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'in:male,female'],
        ]);

        $request->user()->update($validated);
        if (strtolower((string) $request->user()?->role) === 'student') {
            $student = $this->currentStudent($request);
            $student?->update([
                'phone' => $validated['phone'] ?? $student->phone,
                'gender' => $validated['gender'] ?? $student->gender,
            ]);
        }

        return back()->with('success', 'Profile updated.');
    }

    public function updatePortalPassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->user()->update(['password' => Hash::make($validated['password'])]);

        return back()->with('success', 'Password updated.');
    }

    public function portalAttendance(Request $request)
    {
        $student = $this->currentStudent($request);

        return Inertia::render('StudentParent/Attendance', [
            'title' => 'My Attendance',
            'student' => $this->studentPayload($student),
            'linkedStudents' => $this->linkedStudentsPayload($request),
            'selectedStudentId' => $student?->student_id,
            'attendance' => $this->attendanceQuery($student)->get()->map(fn ($attendance) => $this->attendancePayload($attendance)),
        ]);
    }

    public function portalExcuseLetters(Request $request)
    {
        $student = $this->currentStudent($request);

        return Inertia::render('StudentParent/ExcuseLetters', [
            'title' => 'Excuse Letters',
            'student' => $this->studentPayload($student),
            'linkedStudents' => $this->linkedStudentsPayload($request),
            'selectedStudentId' => $student?->student_id,
            'letters' => $student
                ? $student->excuseLetters()->with('submittedBy')->latest()->get()->map(fn (StudentExcuseLetter $letter) => $this->letterPayload($letter))
                : [],
        ]);
    }

    public function storePortalExcuseLetter(Request $request)
    {
        $student = $this->currentStudent($request);
        abort_unless($student, 403);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'reason' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,doc,docx,jpg,jpeg,png'],
        ]);

        $attachment = $request->file('attachment');
        if ($attachment) {
            $validated['attachment_path'] = $attachment->store('student-excuse-letters', 'public');
            $validated['attachment_name'] = $attachment->getClientOriginalName();
        }
        unset($validated['attachment']);

        StudentExcuseLetter::query()->create([
            ...$validated,
            'student_id' => $student->student_id,
            'submitted_by_user_id' => $request->user()->user_id,
            'submitted_by_role' => strtolower((string) $request->user()->role),
        ]);

        return back()->with('success', 'Excuse letter submitted.');
    }

    public function downloadPortalExcuseLetter(Request $request, StudentExcuseLetter $letter)
    {
        $student = $this->currentStudent($request);
        abort_unless($student && (int) $letter->student_id === (int) $student->student_id, 403);

        $letter->loadMissing(['student.section', 'submittedBy']);
        $studentName = trim($letter->student->first_name.' '.$letter->student->last_name);
        $section = $letter->student->section?->section_name ?: 'Section';
        $submittedBy = $letter->submittedBy?->name ?: $studentName;
        $filename = 'excuse-letter-'.$letter->student_excuse_letter_id.'.doc';
        $html = view('documents.excuse-letter', [
            'letter' => $letter,
            'studentName' => $studentName,
            'section' => $section,
            'submittedBy' => $submittedBy,
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'application/msword; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function portalMessages(Request $request)
    {
        $student = $this->currentStudent($request);

        return Inertia::render('StudentParent/Messages', [
            'title' => 'Messages',
            'student' => $this->studentPayload($student),
            'linkedStudents' => $this->linkedStudentsPayload($request),
            'selectedStudentId' => $student?->student_id,
            'messages' => $student ? $this->messageQuery($request, $student)->get()->map(fn ($message) => $this->messagePayload($message)) : [],
            'instructors' => Instructor::query()
                ->with('user:user_id,name,email')
                ->whereHas('user')
                ->get()
                ->map(fn (Instructor $instructor) => [
                    'user_id' => $instructor->user?->user_id,
                    'name' => $instructor->user?->name,
                    'email' => $instructor->user?->email,
                ])
                ->filter(fn ($instructor) => $instructor['user_id'])
                ->values(),
        ]);
    }

    public function storePortalMessage(Request $request)
    {
        $student = $this->currentStudent($request);
        abort_unless($student, 403);

        $validated = $request->validate([
            'instructor_user_id' => ['nullable', 'integer', 'exists:users,user_id'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,doc,docx,jpg,jpeg,png'],
        ]);

        if (! empty($validated['instructor_user_id'])) {
            abort_unless(
                Instructor::query()->where('user_id', $validated['instructor_user_id'])->exists(),
                422,
                'Selected instructor is not available.',
            );
        }

        $attachment = $request->file('attachment');
        $attachmentMime = null;
        $attachmentSize = null;
        if ($attachment) {
            $validated['attachment_path'] = $attachment->store('student-portal-messages', 'public');
            $validated['attachment_name'] = $attachment->getClientOriginalName();
            $attachmentMime = $attachment->getClientMimeType();
            $attachmentSize = $attachment->getSize();
        }
        unset($validated['attachment']);

        $message = StudentPortalMessage::query()->create([
            ...$validated,
            'student_id' => $student->student_id,
            'sender_user_id' => $request->user()->user_id,
            'sender_role' => strtolower((string) $request->user()->role),
        ]);

        if (! empty($validated['instructor_user_id'])) {
            Message::query()->create([
                'instructor_user_id' => $validated['instructor_user_id'],
                'sender_type' => strtolower((string) $request->user()->role),
                'sender_name' => $request->user()->name,
                'sender_email' => $request->user()->email,
                'student_number' => $student->student_number,
                'subject' => $message->subject,
                'body' => $message->body,
                'attachment_path' => $message->attachment_path,
                'attachment_name' => $message->attachment_name,
                'attachment_mime' => $attachmentMime,
                'attachment_size' => $attachmentSize,
            ]);
        }

        return back()->with('success', 'Message sent.');
    }

    public function portalNotifications(Request $request)
    {
        $student = $this->currentStudent($request);

        return Inertia::render('StudentParent/Notifications', [
            'title' => 'Notifications',
            'student' => $this->studentPayload($student),
            'linkedStudents' => $this->linkedStudentsPayload($request),
            'selectedStudentId' => $student?->student_id,
            'notifications' => $student
                ? OnlineClassNotification::query()
                    ->with('onlineClass')
                    ->where('student_id', $student->student_id)
                    ->latest()
                    ->get()
                    ->map(fn (OnlineClassNotification $notification) => [
                        'id' => $notification->online_class_notification_id,
                        'title' => $notification->title,
                        'body' => $notification->body,
                        'event' => $notification->event,
                        'read_at' => $notification->read_at?->toDateTimeString(),
                        'email_sent_at' => $notification->email_sent_at?->toDateTimeString(),
                        'created_at' => $notification->created_at?->toDateTimeString(),
                    ])
                : [],
        ]);
    }

    public function markPortalNotificationRead(Request $request, OnlineClassNotification $notification)
    {
        $student = $this->currentStudent($request);
        abort_unless($student && (int) $notification->student_id === (int) $student->student_id, 403);

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return back();
    }

    private function logActivity(string $action, string $tableName, string $description): void
    {
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'table_name' => $tableName,
            'description' => $description,
        ]);
    }

    private function currentStudent(Request $request): ?Students
    {
        $role = strtolower((string) $request->user()?->role);

        if ($role === 'parent') {
            $query = $request->user()
                ?->linkedStudents()
                ->with(['section', 'strand'])
                ->orderBy('students.student_id');

            if ($request->filled('student_id')) {
                $selected = (clone $query)->where('students.student_id', (int) $request->input('student_id'))->first();
                if ($selected) {
                    return $selected;
                }
            }

            return $query?->first();
        }

        return Students::query()
            ->with(['section', 'strand'])
            ->where('email', $request->user()?->email)
            ->first();
    }

    private function linkedStudentsPayload(Request $request)
    {
        if (strtolower((string) $request->user()?->role) !== 'parent') {
            return [];
        }

        return $request->user()
            ?->linkedStudents()
            ->with(['section', 'strand'])
            ->orderBy('students.student_id')
            ->get()
            ->map(fn (Students $student) => $this->studentPayload($student))
            ->values() ?? [];
    }

    private function studentPayload(?Students $student): ?array
    {
        if (! $student) {
            return null;
        }

        return [
            'student_id' => $student->student_id,
            'student_number' => $student->student_number,
            'name' => trim($student->first_name.' '.$student->last_name),
            'email' => $student->email,
            'phone' => $student->phone,
            'gender' => $student->gender,
            'section' => $student->section?->section_name,
            'strand' => $student->strand?->strand_code,
            'year_level' => $student->year_level,
            'semester' => $student->semester,
            'school_year' => $student->school_year,
            'status' => $student->status,
        ];
    }

    private function attendanceQuery(?Students $student)
    {
        return $student
            ? $student->attendances()->with(['schedule.subject'])->latest('date')
            : Students::query()->whereRaw('1 = 0');
    }

    private function attendancePayload($attendance): array
    {
        return [
            'attendance_id' => $attendance->attendance_id,
            'date' => $attendance->date?->format('Y-m-d'),
            'subject' => $attendance->schedule?->subject?->subject_name ?? $attendance->subject_code,
            'room' => $attendance->room,
            'time_in' => $attendance->time_in,
            'time_out' => $attendance->time_out,
            'class_time' => trim(implode(' - ', array_filter([
                $this->shortTime($attendance->time_start ?? $attendance->schedule?->time_start),
                $this->shortTime($attendance->time_end ?? $attendance->schedule?->time_end),
            ]))),
            'duration' => $this->durationLabel($attendance->time_in, $attendance->time_out),
            'status' => $attendance->status,
        ];
    }

    private function shortTime($value): ?string
    {
        if (! $value) {
            return null;
        }

        return date('g:i A', strtotime((string) $value));
    }

    private function durationLabel($start, $end): ?string
    {
        if (! $start || ! $end) {
            return null;
        }

        $seconds = max(0, strtotime((string) $end) - strtotime((string) $start));
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return trim(($hours ? "{$hours}h " : '').($minutes ? "{$minutes}m" : '')) ?: '0m';
    }

    private function messageQuery(Request $request, Students $student)
    {
        $role = strtolower((string) $request->user()?->role);

        return StudentPortalMessage::query()
            ->with(['sender', 'instructor'])
            ->where('student_id', $student->student_id)
            ->when($role === 'student', fn ($query) => $query->whereIn('sender_role', ['student', 'instructor']))
            ->latest();
    }

    private function messagePayload(StudentPortalMessage $message): array
    {
        return [
            'id' => $message->student_portal_message_id,
            'sender' => $message->sender?->name,
            'sender_role' => $message->sender_role,
            'instructor' => $message->instructor?->name,
            'subject' => $message->subject,
            'body' => $message->body,
            'attachment_name' => $message->attachment_name,
            'attachment_url' => $message->attachment_path ? Storage::disk('public')->url($message->attachment_path) : null,
            'created_at' => $message->created_at?->toDateTimeString(),
        ];
    }

    private function letterPayload(StudentExcuseLetter $letter): array
    {
        return [
            'id' => $letter->student_excuse_letter_id,
            'subject' => $letter->subject,
            'from_date' => $letter->from_date?->format('Y-m-d'),
            'to_date' => $letter->to_date?->format('Y-m-d'),
            'reason' => $letter->reason,
            'status' => $letter->status,
            'submitted_by' => $letter->submittedBy?->name,
            'submitted_by_role' => $letter->submitted_by_role,
            'attachment_name' => $letter->attachment_name,
            'attachment_url' => $letter->attachment_path ? Storage::disk('public')->url($letter->attachment_path) : null,
            'download_url' => route('student-parent.excuse-letters.download', $letter),
            'created_at' => $letter->created_at?->toDateTimeString(),
        ];
    }
}
