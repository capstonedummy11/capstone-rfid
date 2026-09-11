<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AcademicYear;
use App\Models\Instructor;
use App\Models\Message;
use App\Models\OnlineClass;
use App\Models\OnlineClassNotification;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Strand;
use App\Models\StudentExcuseLetter;
use App\Models\StudentEnrollment;
use App\Models\StudentPortalMessage;
use App\Models\Students;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\CompreFaceService;
use App\Services\ExcuseLetterPdfService;
use App\Services\MessengerEmailNotificationService;
use App\Services\OnlineClassAttendanceFinalizer;
use App\Services\StudentEnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class StudentsController
{
    public function __construct(
        private readonly MessengerEmailNotificationService $emailNotifications,
        private readonly OnlineClassAttendanceFinalizer $onlineAttendanceFinalizer,
        private readonly StudentEnrollmentService $studentEnrollmentService,
    ) {}

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
            'semester' => trim((string) $request->input('semester', '')),
            'status' => trim((string) $request->input('status', '')),
        ];

        $user = $request->user();
        $role = strtolower(trim((string) $user?->role));
        $isAdmin = $role === 'admin';
        $isInstructor = $role === 'instructor';
        $handledSectionIds = collect();
        $selectedAcademicYear = ! in_array($filters['school_year'], ['', 'all'], true)
            ? AcademicYear::query()->where('name', $filters['school_year'])->first()
            : ($filters['school_year'] === 'all' ? null : AcademicYear::currentOrLatest());
        if ($filters['school_year'] === '' && $selectedAcademicYear) {
            $filters['school_year'] = $selectedAcademicYear->name;
        }
        if ($filters['semester'] === '' && $selectedAcademicYear?->active_semester) {
            $filters['semester'] = $selectedAcademicYear->active_semester;
        }

        if ($isInstructor) {
            $instructorId = Instructor::query()
                ->where('user_id', $user?->user_id)
                ->value('instructor_id');

            $handledSectionIds = Section::query()
                ->whereHas('schedules', fn ($scheduleQuery) => $scheduleQuery->where('instructor_id', $instructorId ?: 0))
                ->pluck('section_id');
        }

        $query = Students::query()->with([
            'section',
            'strand',
            'parentUsers',
            'enrollments.academicYear',
            'enrollments.section',
            'enrollments.strand',
        ]);

        if ($selectedAcademicYear) {
            $query->whereHas('enrollments', fn ($enrollment) => $enrollment->where('academic_year_id', $selectedAcademicYear->academic_year_id)->when($filters['semester'] !== '', fn ($term) => $term->where('semester', $filters['semester'])));
        }

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
            $selectedAcademicYear
                ? $query->whereHas('enrollments', fn ($enrollment) => $enrollment->where('academic_year_id', $selectedAcademicYear->academic_year_id)->where('strand_id', $filters['strand']))
                : $query->where('strand_id', $filters['strand']);
        }

        if ($filters['section'] !== '') {
            if ($isInstructor && ! $handledSectionIds->contains((int) $filters['section'])) {
                $query->whereRaw('1 = 0');
            } else {
                $selectedAcademicYear
                    ? $query->whereHas('enrollments', fn ($enrollment) => $enrollment->where('academic_year_id', $selectedAcademicYear->academic_year_id)->where('section_id', $filters['section']))
                    : $query->where('section_id', $filters['section']);
            }
        }

        if ($filters['year'] !== '') {
            $selectedAcademicYear
                ? $query->whereHas('enrollments', fn ($enrollment) => $enrollment->where('academic_year_id', $selectedAcademicYear->academic_year_id)->where('year_level', $filters['year']))
                : $query->where('year_level', $filters['year']);
        }

        if (! in_array($filters['school_year'], ['', 'all'], true)) {
            if (! $selectedAcademicYear) {
                $query->where('school_year', $filters['school_year']);
            }
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        $students = $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function (Students $student) use ($selectedAcademicYear) {
                $placement = $selectedAcademicYear
                    ? $student->enrollments->first(fn ($enrollment) => (int) $enrollment->academic_year_id === (int) $selectedAcademicYear->academic_year_id && ($filters['semester'] === '' || $enrollment->semester === $filters['semester']))
                    : $student->enrollments->sortByDesc('student_enrollment_id')->first();
                return [
                    'student_id' => $student->student_id,
                    'student_number' => $student->student_number,
                    'first_name' => $student->first_name,
                    'middle_name' => $student->middle_name,
                    'last_name' => $student->last_name,
                    'email' => $student->email,
                    'phone' => $student->phone,
                    'gender' => $student->gender,
                    'strand_id' => $placement?->strand_id ?? $student->strand_id,
                    'strand_code' => $placement?->strand?->strand_code ?? $student->strand?->strand_code,
                    'section_id' => $placement?->section_id ?? $student->section_id,
                    'section_name' => $placement?->section?->section_name ?? $student->section?->section_name,
                    'year_level' => $placement?->year_level ?? $student->year_level,
                    'semester' => $placement?->semester ?? $student->semester,
                    'school_year' => $placement?->academicYear?->name ?? $student->school_year,
                    'rfid_tag' => $student->rfid_tag,
                    'face_images' => $student->face_images ?? [],
                    'status' => $student->status ?? 'active',
                    'enrollments' => $student->enrollments
                        ->sortByDesc(fn ($enrollment) => $enrollment->academicYear?->starts_on)
                        ->map(fn ($enrollment) => [
                            'student_enrollment_id' => $enrollment->student_enrollment_id,
                            'academic_year' => $enrollment->academicYear?->name,
                            'semester' => $enrollment->semester,
                            'year_level' => $enrollment->year_level,
                            'section_name' => $enrollment->section?->section_name,
                            'strand_code' => $enrollment->strand?->strand_code,
                            'status' => $enrollment->status,
                        ])
                        ->values(),
                    'parents' => $student->parentUsers
                        ->map(fn (User $parent) => $this->parentPayload($parent))
                        ->values(),
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
            'semesterOptions' => ['1st Semester', '2nd Semester'],
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
        $existingUser = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($validated['email'])])
            ->first();

        if ($existingUser && strtolower((string) $existingUser->role) !== 'student') {
            return back()->withErrors([
                'email' => 'This email already belongs to a non-student account.',
            ]);
        }

        $student = null;
        DB::transaction(function () use ($validated, &$student, $existingUser) {
            $placement = collect($validated)->only(['section_id', 'strand_id', 'year_level', 'semester', 'school_year', 'status'])->all();
            $student = Students::create(collect($validated)->except(['section_id', 'strand_id', 'year_level', 'semester', 'school_year'])->all());
            $this->createOrUpdateStudentAccount($student, $existingUser);
            $this->studentEnrollmentService->syncPlacement($student, $placement);
        });

        $this->logActivity('create', 'students', 'Created student '.$student->student_number);

        return back()->with('success', 'Student added successfully. Student account created with default password '.$this->defaultStudentPassword($student).'.');
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

        $existingUser = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($validated['email'])])
            ->where('role', '!=', 'student')
            ->first();

        if ($existingUser) {
            return back()->withErrors([
                'email' => 'This email already belongs to a non-student account.',
            ]);
        }

        $studentUser = $this->studentAccountFor($student);

        DB::transaction(function () use ($student, $validated, $studentUser) {
            $placement = collect($validated)->only(['section_id', 'strand_id', 'year_level', 'semester', 'school_year', 'status'])->all();
            $student->update(collect($validated)->except(['section_id', 'strand_id', 'year_level', 'semester', 'school_year'])->all());
            $this->createOrUpdateStudentAccount($student, $studentUser);
            $this->studentEnrollmentService->syncPlacement($student, $placement);
        });

        $this->logActivity('update', 'students', 'Updated student '.$student->student_number);

        return back()->with('success', 'Student updated successfully.');
    }

    public function resetStudentAccountPassword(int $id)
    {
        $student = Students::query()->findOrFail($id);
        $user = $this->createOrUpdateStudentAccount($student);
        $password = $this->defaultStudentPassword($student);

        $user->forceFill([
            'password' => Hash::make($password),
            'must_change_password' => true,
        ])->save();

        $this->logActivity('update', 'users', 'Reset student portal password for '.$student->student_number);

        return back()->with('success', 'Student account password reset to '.$password.'.');
    }

    public function storeParent(Request $request, int $id)
    {
        $student = Students::query()->findOrFail($id);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'in:male,female'],
            'relationship' => ['required', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
        ]);

        $parent = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($validated['email'])])
            ->first();

        if ($parent && strtolower((string) $parent->role) !== 'parent') {
            return back()->withErrors([
                'email' => 'This email already belongs to a non-parent account.',
            ]);
        }

        if (! $parent && blank($validated['password'] ?? null)) {
            return back()->withErrors([
                'password' => 'Password is required when creating a new parent account.',
            ]);
        }

        if (! $parent) {
            $parent = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'must_change_password' => true,
                'role' => 'parent',
                'phone' => $validated['phone'] ?? null,
                'gender' => $validated['gender'] ?? null,
            ]);

            $this->logActivity('create', 'users', 'Created parent account '.$parent->email.' for student '.$student->student_number);
        } else {
            $payload = [
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'gender' => $validated['gender'] ?? null,
            ];

            if (! blank($validated['password'] ?? null)) {
                $payload['password'] = Hash::make($validated['password']);
            }

            $parent->update($payload);
        }

        $student->parentUsers()->syncWithoutDetaching([
            $parent->user_id => ['relationship' => $validated['relationship']],
        ]);

        $this->logActivity('update', 'parent_student_links', 'Linked parent '.$parent->email.' to student '.$student->student_number);

        return back()->with('success', 'Parent account linked to student.');
    }

    public function updateParent(Request $request, int $id, int $parent)
    {
        $student = Students::query()->findOrFail($id);
        $parentUser = $student->parentUsers()
            ->where('users.user_id', $parent)
            ->whereRaw('LOWER(role) = ?', ['parent'])
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($parentUser->user_id, 'user_id')],
            'phone' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'in:male,female'],
            'relationship' => ['required', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
        ]);

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'gender' => $validated['gender'] ?? null,
        ];

        if (! blank($validated['password'] ?? null)) {
            $payload['password'] = Hash::make($validated['password']);
        }

        $parentUser->update($payload);
        $student->parentUsers()->updateExistingPivot($parentUser->user_id, [
            'relationship' => $validated['relationship'],
        ]);

        $this->logActivity('update', 'parent_student_links', 'Updated parent '.$parentUser->email.' for student '.$student->student_number);

        return back()->with('success', 'Parent account updated.');
    }

    public function destroyParent(Request $request, int $id, int $parent)
    {
        $student = Students::query()->findOrFail($id);
        $parentUser = $student->parentUsers()
            ->where('users.user_id', $parent)
            ->whereRaw('LOWER(role) = ?', ['parent'])
            ->firstOrFail();

        $student->parentUsers()->detach($parentUser->user_id);

        $this->logActivity('delete', 'parent_student_links', 'Unlinked parent '.$parentUser->email.' from student '.$student->student_number);

        return back()->with('success', 'Parent account unlinked from student.');
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
        $enrollment = $this->portalEnrollment($request, $student);
        $attendance = $this->combinedAttendancePayloads($student, $enrollment);

        return Inertia::render('StudentParent/Dashboard', [
            'title' => 'Student Dashboard',
            'student' => $this->studentPayload($student, $enrollment),
            'linkedStudents' => $this->linkedStudentsPayload($request),
            'selectedStudentId' => $student?->student_id,
            'stats' => [
                'present' => $attendance->where('status', 'present')->count(),
                'late' => $attendance->where('status', 'late')->count(),
                'excuse_letters' => $student?->excuseLetters()->when($enrollment, fn ($query) => $query->where('academic_year_id', $enrollment->academic_year_id))->count() ?? 0,
                'messages' => $student ? $this->messageQuery($request, $student)->count() : 0,
                'online_classes' => $student
                    ? OnlineClass::query()->when($enrollment, fn ($query) => $query->where('academic_year_id', $enrollment->academic_year_id)->where('section_id', $enrollment->section_id))->where('status', 'scheduled')->count()
                    : 0,
            ],
            'recentAttendance' => $attendance->take(5),
            'attendance' => $attendance->take(100),
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

        $this->logActivity('update', 'users', 'Updated student/parent portal profile for '.$request->user()->email);

        return back()->with('success', 'Profile updated.');
    }

    public function updatePortalPassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->user()->update(['password' => Hash::make($validated['password'])]);

        $this->logActivity('update', 'users', 'Updated student/parent portal password for '.$request->user()->email);

        return back()->with('success', 'Password updated.');
    }

    public function portalAttendance(Request $request)
    {
        $student = $this->currentStudent($request);
        $enrollment = $this->portalEnrollment($request, $student);

        return Inertia::render('StudentParent/Attendance', [
            'title' => 'My Attendance',
            'student' => $this->studentPayload($student, $enrollment),
            'linkedStudents' => $this->linkedStudentsPayload($request),
            'selectedStudentId' => $student?->student_id,
            'attendance' => $this->combinedAttendancePayloads($student, $enrollment),
            'academicYears' => $this->portalAcademicYearOptions($student),
            'selectedAcademicYearId' => $enrollment?->academic_year_id,
        ]);
    }

    public function portalExcuseLetters(Request $request)
    {
        $student = $this->currentStudent($request);
        $enrollment = $this->portalEnrollment($request, $student);
        $parentPortalEnabled = SystemSetting::boolean(SystemSetting::PARENT_PORTAL_ENABLED, false);

        return Inertia::render('StudentParent/ExcuseLetters', [
            'title' => 'Excuse Letters',
            'student' => $this->studentPayload($student, $enrollment),
            'linkedStudents' => $this->linkedStudentsPayload($request),
            'selectedStudentId' => $student?->student_id,
            'currentUserRole' => strtolower((string) $request->user()?->role),
            'parentPortalEnabled' => $parentPortalEnabled,
            'parentExcuseLettersEnabled' => $parentPortalEnabled
                && SystemSetting::boolean(SystemSetting::PARENT_EXCUSE_LETTERS_ENABLED, false),
            'recipientSuggestions' => $student ? $this->teacherSuggestionPayload($student) : [],
            'letters' => $student
                ? $student->excuseLetters()->with(['submittedBy', 'parentApprovedBy', 'academicYear'])->when($enrollment, fn ($query) => $query->where('academic_year_id', $enrollment->academic_year_id))->latest()->get()->map(fn (StudentExcuseLetter $letter) => $this->letterPayload($letter, $request))
                : [],
        ]);
    }

    public function storePortalExcuseLetter(Request $request)
    {
        $student = $this->currentStudent($request);
        abort_unless($student, 403);
        $enrollment = $this->portalEnrollment($request, $student);
        abort_if($enrollment && $enrollment->academicYear?->status !== AcademicYear::STATUS_ACTIVE, 422, 'Excuse letters can only be submitted for the active academic year.');
        $role = strtolower((string) $request->user()?->role);
        $parentPortalEnabled = SystemSetting::boolean(SystemSetting::PARENT_PORTAL_ENABLED, false);
        $parentExcuseLettersEnabled = $parentPortalEnabled
            && SystemSetting::boolean(SystemSetting::PARENT_EXCUSE_LETTERS_ENABLED, false);

        abort_if($role === 'parent' && ! $parentExcuseLettersEnabled, 403);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'reason' => ['required', 'string', 'max:5000'],
            'parent_signature' => [Rule::requiredIf($role === 'parent'), 'nullable', 'string', 'max:255'],
            'recipient_user_ids' => ['nullable', 'array'],
            'recipient_user_ids.*' => ['integer', 'exists:users,user_id'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,doc,docx,jpg,jpeg,png'],
        ]);
        abort_if(
            $enrollment && ($validated['from_date'] < $enrollment->academicYear->starts_on->toDateString()
                || $validated['to_date'] > $enrollment->academicYear->ends_on->toDateString()),
            422,
            'Excuse-letter dates must fall within the active academic year.'
        );

        $attachment = $request->file('attachment');
        if ($attachment) {
            $validated['attachment_path'] = $attachment->store('student-excuse-letters', 'public');
            $validated['attachment_name'] = $attachment->getClientOriginalName();
        }
        unset($validated['attachment']);
        unset($validated['parent_signature']);
        $validated['recipient_user_ids'] = $this->validTeacherRecipientIds($student, $validated['recipient_user_ids'] ?? []);

        $letter = StudentExcuseLetter::query()->create([
            ...$validated,
            'student_id' => $student->student_id,
            'academic_year_id' => $enrollment?->academic_year_id,
            'student_enrollment_id' => $enrollment?->student_enrollment_id,
            'submitted_by_user_id' => $request->user()->user_id,
            'submitted_by_role' => $role,
            'status' => $role === 'parent' || ! $parentPortalEnabled ? 'approved' : 'pending_parent_approval',
            'parent_signature' => $role === 'parent' ? $request->input('parent_signature') : null,
            'parent_approved_by_user_id' => $role === 'parent' ? $request->user()->user_id : null,
            'parent_approved_at' => $role === 'parent' ? now() : null,
        ]);

        $this->logActivity('create', 'student_excuse_letters', 'Submitted excuse letter '.$letter->student_excuse_letter_id.' for student '.$student->student_number);

        $teacherMessageCount = $role === 'parent' || ! $parentPortalEnabled
            ? $this->sendApprovedExcuseLetterToTeachers($letter->fresh(['student', 'submittedBy', 'parentApprovedBy']), $request->user())
            : 0;

        if ($role !== 'parent' && $parentPortalEnabled) {
            $this->notifyParentsExcuseLetterNeedsApproval(
                $letter->fresh(['student.parentUsers', 'submittedBy']),
            );
        }

        return back()->with('success', match (true) {
            $role !== 'parent' && $parentPortalEnabled => 'Excuse letter submitted.',
            $role !== 'parent' && $teacherMessageCount > 0 => 'Excuse letter submitted and sent to the teacher.',
            $role !== 'parent' => 'Excuse letter submitted, but no assigned teacher was found for this section.',
            $teacherMessageCount > 0 => 'Excuse letter submitted and sent to the teacher.',
            default => 'Excuse letter submitted, but no assigned teacher was found for this section.',
        });
    }

    public function approvePortalExcuseLetter(Request $request, StudentExcuseLetter $letter)
    {
        abort_if(! SystemSetting::boolean(SystemSetting::PARENT_PORTAL_ENABLED, false), 403, 'Parent portal is disabled.');
        $student = $this->currentStudent($request);
        abort_unless(
            strtolower((string) $request->user()?->role) === 'parent'
            && $student
            && (int) $letter->student_id === (int) $student->student_id,
            403,
        );

        if ((string) $letter->submitted_by_role !== 'student') {
            return back()->withErrors(['letter' => 'Only student-created excuse letters need parent approval.']);
        }

        $validated = $request->validate([
            'parent_signature' => ['required', 'string', 'max:255'],
            'parent_approval_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $letter->update([
            'status' => 'approved',
            'parent_signature' => $validated['parent_signature'],
            'parent_approval_notes' => $validated['parent_approval_notes'] ?? null,
            'parent_approved_by_user_id' => $request->user()->user_id,
            'parent_approved_at' => now(),
        ]);

        $this->logActivity('update', 'student_excuse_letters', 'Parent approved excuse letter '.$letter->student_excuse_letter_id.' for student '.$student->student_number);
        $teacherMessageCount = $this->sendApprovedExcuseLetterToTeachers($letter->fresh(['student', 'submittedBy', 'parentApprovedBy']), $request->user());

        return back()->with('success', $teacherMessageCount > 0
            ? 'Excuse letter approved and sent to the teacher.'
            : 'Excuse letter approved, but no assigned teacher was found for this section.');
    }

    public function downloadPortalExcuseLetter(Request $request, StudentExcuseLetter $letter)
    {
        $student = $this->currentStudent($request);
        abort_unless($student && (int) $letter->student_id === (int) $student->student_id, 403);
        abort_if((string) $letter->status === 'pending_parent_approval', 422, 'Parent approval is required before downloading this excuse letter.');

        $letter->loadMissing(['student.section', 'studentEnrollment.section', 'submittedBy', 'parentApprovedBy']);
        $studentName = trim($letter->student->first_name.' '.$letter->student->last_name);
        $section = $letter->studentEnrollment?->section?->section_name ?? $letter->student->section?->section_name ?? 'Section';
        $submittedBy = $letter->submittedBy?->name ?: $studentName;
        $filename = 'excuse-letter-'.$letter->student_excuse_letter_id.'.pdf';
        $pdf = app(ExcuseLetterPdfService::class)->render($letter, $studentName, $section, $submittedBy);

        $this->logActivity('download', 'student_excuse_letters', 'Downloaded generated excuse letter '.$letter->student_excuse_letter_id.' for student '.$student->student_number);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function downloadPortalExcuseLetterAttachment(Request $request, StudentExcuseLetter $letter)
    {
        $student = $this->currentStudent($request);
        abort_unless($student && (int) $letter->student_id === (int) $student->student_id, 403);
        abort_unless($letter->attachment_path && Storage::disk('public')->exists($letter->attachment_path), 404);

        $this->logActivity('download', 'student_excuse_letters', 'Downloaded excuse letter attachment '.$letter->student_excuse_letter_id.' for student '.$student->student_number);

        return Storage::disk('public')->download($letter->attachment_path, $letter->attachment_name ?: 'excuse-letter-attachment');
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
            'instructor_user_id' => ['required', 'integer', 'exists:users,user_id'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,doc,docx,jpg,jpeg,png'],
        ]);

        abort_unless(
            Instructor::query()->where('user_id', $validated['instructor_user_id'])->exists(),
            422,
            'Selected instructor is not available.',
        );

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

        $validated['subject'] = filled($validated['subject'] ?? null)
            ? $validated['subject']
            : 'Student portal conversation';

        $message = StudentPortalMessage::query()->create([
            ...$validated,
            'student_id' => $student->student_id,
            'sender_user_id' => $request->user()->user_id,
            'recipient_user_id' => $validated['instructor_user_id'],
            'sender_role' => strtolower((string) $request->user()->role),
        ]);

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

        $this->logActivity('create', 'student_portal_messages', 'Sent portal message '.$message->student_portal_message_id.' for student '.$student->student_number.' to instructor user '.$validated['instructor_user_id']);
        $recipient = User::query()->find($validated['instructor_user_id']);
        if ($recipient) {
            $this->emailNotifications->notify($request->user(), $recipient, $message);
        }

        return back()->with('success', 'Message sent.');
    }

    public function portalNotifications(Request $request)
    {
        $student = $this->currentStudent($request);
        $enrollment = $this->portalEnrollment($request, $student);

        return Inertia::render('StudentParent/Notifications', [
            'title' => 'Notifications',
            'student' => $this->studentPayload($student, $enrollment),
            'linkedStudents' => $this->linkedStudentsPayload($request),
            'selectedStudentId' => $student?->student_id,
            'notifications' => $student
                ? OnlineClassNotification::query()
                    ->with('onlineClass')
                    ->where('student_id', $student->student_id)
                    ->when($enrollment, fn ($query) => $query->where('academic_year_id', $enrollment->academic_year_id))
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
            $this->logActivity('update', 'online_class_notifications', 'Marked online class notification '.$notification->online_class_notification_id.' as read for student '.$student->student_number);
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
            abort_if(! SystemSetting::boolean(SystemSetting::PARENT_PORTAL_ENABLED, false), 403, 'Parent portal is disabled.');

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

    private function portalEnrollment(Request $request, ?Students $student): ?StudentEnrollment
    {
        if (! $student) {
            return null;
        }

        $query = $student->enrollments()->with(['academicYear', 'section', 'strand']);
        if ($request->filled('academic_year_id')) {
            return (clone $query)->where('academic_year_id', (int) $request->input('academic_year_id'))->latest('student_enrollment_id')->firstOrFail();
        }

        $activeYearId = AcademicYear::currentOrLatest()?->academic_year_id;

        return ($activeYearId ? (clone $query)->where('academic_year_id', $activeYearId)->latest('student_enrollment_id')->first() : null)
            ?? $query->latest('student_enrollment_id')->first();
    }

    private function portalAcademicYearOptions(?Students $student)
    {
        return $student?->enrollments()->with('academicYear')->get()
            ->filter(fn (StudentEnrollment $enrollment) => $enrollment->academicYear)
            ->sortByDesc(fn (StudentEnrollment $enrollment) => $enrollment->academicYear->starts_on)
            ->map(fn (StudentEnrollment $enrollment) => [
                'academic_year_id' => $enrollment->academic_year_id,
                'name' => $enrollment->academicYear->name,
                'status' => $enrollment->academicYear->status,
            ])->unique('academic_year_id')->values() ?? collect();
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

    private function studentPayload(?Students $student, ?StudentEnrollment $enrollment = null): ?array
    {
        if (! $student) {
            return null;
        }

        $enrollment ??= $student->currentEnrollment();
        if (! $enrollment) {
            \App\Services\LegacyAcademicFallbackMonitor::record('student_portal.profile_placement', ['student_id' => $student->student_id]);
        }
        $enrollment?->loadMissing(['academicYear', 'section', 'strand']);

        return [
            'student_id' => $student->student_id,
            'student_number' => $student->student_number,
            'name' => trim($student->first_name.' '.$student->last_name),
            'email' => $student->email,
            'phone' => $student->phone,
            'gender' => $student->gender,
            'section' => $enrollment?->section?->section_name ?? $student->section?->section_name,
            'strand' => $enrollment?->strand?->strand_code ?? $student->strand?->strand_code,
            'year_level' => $enrollment?->year_level ?? $student->year_level,
            'semester' => $enrollment?->semester ?? $student->semester,
            'school_year' => $enrollment?->academicYear?->name ?? $student->school_year,
            'academic_year_id' => $enrollment?->academic_year_id,
            'student_enrollment_id' => $enrollment?->student_enrollment_id,
            'status' => $student->status,
        ];
    }

    private function parentPayload(User $parent): array
    {
        return [
            'id' => $parent->user_id,
            'name' => $parent->name,
            'email' => $parent->email,
            'phone' => $parent->phone,
            'gender' => $parent->gender,
            'relationship' => $parent->pivot?->relationship ?? 'parent',
        ];
    }

    private function createOrUpdateStudentAccount(Students $student, ?User $studentUser = null): User
    {
        $studentUser ??= $this->studentAccountFor($student);

        $payload = [
            'name' => trim($student->first_name.' '.$student->last_name),
            'email' => $student->email,
            'role' => 'student',
            'phone' => $student->phone,
            'gender' => $student->gender,
            'rfid_tag' => null,
        ];

        if (! $studentUser) {
            $payload['password'] = Hash::make($this->defaultStudentPassword($student));
            $payload['must_change_password'] = true;

            return User::query()->create($payload);
        }

        $studentUser->update($payload);

        return $studentUser;
    }

    private function studentAccountFor(Students $student): ?User
    {
        if (blank($student->email)) {
            return null;
        }

        return User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower((string) $student->email)])
            ->whereRaw('LOWER(role) = ?', ['student'])
            ->first();
    }

    private function defaultStudentPassword(Students $student): string
    {
        $password = preg_replace('/\s+/', '', trim($student->first_name.$student->last_name));

        return $password !== '' ? $password : (string) $student->student_number;
    }

    private function sendApprovedExcuseLetterToTeachers(StudentExcuseLetter $letter, User $sender): int
    {
        $letter->loadMissing(['student.section', 'submittedBy', 'parentApprovedBy']);
        $student = $letter->student;
        if (! $student) {
            return 0;
        }

        $teacherUsers = $this->teacherUsersForStudent($student);
        $selectedRecipientIds = collect($letter->recipient_user_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($selectedRecipientIds->isNotEmpty()) {
            $teacherUsers = $teacherUsers
                ->whereIn('user_id', $selectedRecipientIds)
                ->values();
        }

        if ($teacherUsers->isEmpty()) {
            $this->logActivity('warning', 'student_excuse_letters', 'Approved excuse letter '.$letter->student_excuse_letter_id.' was not sent because no teacher is assigned to student '.$student->student_number);

            return 0;
        }

        $subject = 'Approved Excuse Letter: '.$letter->subject;
        $body = $this->approvedExcuseLetterMessageBody($letter, $student, $sender);
        [$attachmentPath, $attachmentName] = $this->storeApprovedExcuseLetterPdf($letter, $student);
        $attachmentMime = $this->storedAttachmentMime($attachmentPath);
        $attachmentSize = $this->storedAttachmentSize($attachmentPath);

        foreach ($teacherUsers as $teacher) {
            $message = StudentPortalMessage::query()->create([
                'student_id' => $student->student_id,
                'sender_user_id' => $sender->user_id,
                'recipient_user_id' => $teacher->user_id,
                'sender_role' => strtolower((string) $sender->role),
                'instructor_user_id' => $teacher->user_id,
                'subject' => $subject,
                'body' => $body,
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'attachment_mime' => $attachmentMime,
                'attachment_size' => $attachmentSize,
            ]);

            Message::query()->create([
                'instructor_user_id' => $teacher->user_id,
                'sender_type' => strtolower((string) $sender->role),
                'sender_name' => $sender->name,
                'sender_email' => $sender->email,
                'student_number' => $student->student_number,
                'subject' => $message->subject,
                'body' => $message->body,
                'attachment_path' => $message->attachment_path,
                'attachment_name' => $message->attachment_name,
                'attachment_mime' => $message->attachment_mime,
                'attachment_size' => $message->attachment_size,
            ]);

            $this->emailApprovedExcuseLetterToTeacher(
                $teacher,
                $letter,
                $student,
                $sender,
                $body,
                $attachmentPath,
                $attachmentName,
            );
        }

        $this->logActivity('create', 'student_portal_messages', 'Sent approved excuse letter '.$letter->student_excuse_letter_id.' to '.$teacherUsers->count().' teacher account(s).');

        return $teacherUsers->count();
    }

    private function notifyParentsExcuseLetterNeedsApproval(StudentExcuseLetter $letter): int
    {
        $student = $letter->student;
        if (! $student) {
            return 0;
        }

        $studentName = trim($student->first_name.' '.$student->last_name);
        $approvalUrl = route('student-parent.excuse-letters.index', [
            'student_id' => $student->student_id,
        ]);
        $sent = 0;

        foreach ($student->parentUsers->filter(fn (User $parent) => filter_var($parent->email, FILTER_VALIDATE_EMAIL)) as $parent) {
            try {
                Mail::raw(
                    implode("\n\n", [
                        "Hello {$parent->name},",
                        "{$studentName} submitted an excuse letter for {$letter->subject} covering {$letter->from_date?->format('F j, Y')} to {$letter->to_date?->format('F j, Y')}.",
                        'Please sign in to the parent portal, review the letter, and provide your approval and signature.',
                        "Review and sign: {$approvalUrl}",
                    ]),
                    fn ($message) => $message
                        ->to($parent->email)
                        ->subject('Excuse Letter Awaiting Parent Signature'),
                );
                $sent++;
            } catch (\Throwable $exception) {
                Log::warning('Parent excuse-letter email could not be sent.', [
                    'letter_id' => $letter->student_excuse_letter_id,
                    'parent_user_id' => $parent->user_id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $this->logActivity(
            $sent > 0 ? 'email' : 'warning',
            'student_excuse_letters',
            'Sent parent signature notification for excuse letter '.$letter->student_excuse_letter_id.' to '.$sent.' parent account(s).',
        );

        return $sent;
    }

    private function emailApprovedExcuseLetterToTeacher(
        User $teacher,
        StudentExcuseLetter $letter,
        Students $student,
        User $sender,
        string $body,
        string $attachmentPath,
        string $attachmentName,
    ): void {
        if (! filter_var($teacher->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Mail::raw(
                $body."\n\nThe signed excuse-letter PDF is attached. You can also review it in Messenger.",
                fn ($message) => $message
                    ->to($teacher->email)
                    ->subject('Approved Excuse Letter: '.$letter->subject)
                    ->attach(Storage::disk('public')->path($attachmentPath), [
                        'as' => $attachmentName,
                        'mime' => 'application/pdf',
                    ]),
            );
        } catch (\Throwable $exception) {
            Log::warning('Instructor excuse-letter email could not be sent.', [
                'letter_id' => $letter->student_excuse_letter_id,
                'student_id' => $student->student_id,
                'teacher_user_id' => $teacher->user_id,
                'approved_by_user_id' => $sender->user_id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function storeApprovedExcuseLetterPdf(StudentExcuseLetter $letter, Students $student): array
    {
        $studentName = trim($student->first_name.' '.$student->last_name);
        $section = $student->section?->section_name ?: 'Section';
        $submittedBy = $letter->submittedBy?->name ?: $studentName;
        $filename = 'excuse-letter-'.$letter->student_excuse_letter_id.'.pdf';
        $path = 'student-excuse-letters/generated/'.$filename;
        $pdf = app(ExcuseLetterPdfService::class)->render(
            $letter,
            $studentName,
            $section,
            $submittedBy,
        );

        Storage::disk('public')->put($path, $pdf);

        return [$path, $filename];
    }

    private function teacherUsersForStudent(Students $student)
    {
        $teacherIds = Schedule::query()
            ->where('section_id', $student->section_id)
            ->whereNotNull('instructor_id')
            ->with('instructor.user:user_id,name,email,role')
            ->get()
            ->map(fn (Schedule $schedule) => $schedule->instructor?->user)
            ->filter(fn (?User $user) => $user && strtolower((string) $user->role) === 'instructor')
            ->unique('user_id')
            ->pluck('user_id');

        return User::query()
            ->whereIn('user_id', $teacherIds)
            ->orderBy('name')
            ->get();
    }

    private function teacherSuggestionPayload(Students $student)
    {
        return $this->teacherUsersForStudent($student)
            ->map(fn (User $teacher) => [
                'user_id' => $teacher->user_id,
                'name' => $teacher->name,
                'email' => $teacher->email,
                'label' => trim($teacher->name.' <'.$teacher->email.'>'),
            ])
            ->values();
    }

    private function validTeacherRecipientIds(Students $student, array $recipientIds): ?array
    {
        $validIds = $this->teacherUsersForStudent($student)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $selectedIds = collect($recipientIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => in_array($id, $validIds, true))
            ->unique()
            ->values()
            ->all();

        return $selectedIds === [] ? null : $selectedIds;
    }

    private function approvedExcuseLetterMessageBody(StudentExcuseLetter $letter, Students $student, User $sender): string
    {
        $studentName = trim($student->first_name.' '.$student->last_name);
        $dateRange = trim(($letter->from_date?->format('Y-m-d') ?? '').' to '.($letter->to_date?->format('Y-m-d') ?? ''));
        $approvedAt = $letter->parent_approved_at?->format('Y-m-d g:i A') ?? now()->format('Y-m-d g:i A');

        return trim(implode("\n\n", array_filter([
            'An excuse letter has been signed by a parent and is ready for teacher review.',
            "Student: {$studentName} ({$student->student_number})",
            'Section: '.($student->section?->section_name ?? 'N/A'),
            "Subject: {$letter->subject}",
            "Covered Dates: {$dateRange}",
            "Reason:\n{$letter->reason}",
            "Parent Signature: {$letter->parent_signature}",
            "Approved By: {$sender->name}",
            "Approved At: {$approvedAt}",
            $letter->parent_approval_notes ? "Parent Notes:\n{$letter->parent_approval_notes}" : null,
        ])));
    }

    private function storedAttachmentMime(?string $path): ?string
    {
        return $path && Storage::disk('public')->exists($path)
            ? Storage::disk('public')->mimeType($path)
            : null;
    }

    private function storedAttachmentSize(?string $path): ?int
    {
        return $path && Storage::disk('public')->exists($path)
            ? Storage::disk('public')->size($path)
            : null;
    }

    private function attendanceQuery(?Students $student, ?StudentEnrollment $enrollment = null)
    {
        return $student
            ? $student->attendances()->with(['schedule.subject'])
                ->when($enrollment, fn ($query) => $query->where('academic_year_id', $enrollment->academic_year_id))
                ->latest('date')
            : Students::query()->whereRaw('1 = 0');
    }

    private function attendancePayload($attendance): array
    {
        $sessionId = DB::table('attendance_sessions')
            ->where('schedule_id', $attendance->schedule_id)
            ->whereDate('date', $attendance->date)
            ->where('room', $attendance->room)
            ->where('subject_code', $attendance->subject_code)
            ->orderByDesc('attendance_id')
            ->value('attendance_id');
        $evidenceLogs = $sessionId
            ? DB::table('attendance_logs')
                ->where('attendance_id', $sessionId)
                ->where('student_id', $attendance->student_id)
                ->where(function ($query) use ($attendance) {
                    $query
                        ->where('main_attendance_id', $attendance->attendance_id)
                        ->orWhereNull('main_attendance_id');
                })
                ->orderBy('tap_sequence_number')
                ->orderBy('id')
                ->get([
                    'id',
                    'tap_type',
                    'tap_sequence_number',
                    'tap_datetime',
                    'validation_result',
                    'remarks',
                    'time_in_face_path',
                    'time_out_face_path',
                    'verification_method',
                ])
            : collect();

        $timeInEvidence = $evidenceLogs->first(fn ($log) => $log->tap_type === 'Check-in' && $log->time_in_face_path)
            ?? $evidenceLogs->first(fn ($log) => $log->time_in_face_path);
        $timeOutEvidence = $evidenceLogs->first(fn ($log) => $log->tap_type === 'Check-out' && $log->time_out_face_path)
            ?? $evidenceLogs->first(fn ($log) => $log->time_out_face_path);

        return [
            'attendance_id' => $attendance->attendance_id,
            'source' => 'rfid',
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
            'sort_time' => (string) ($attendance->time_in ?? $attendance->time_start ?? $attendance->schedule?->time_start ?? '00:00:00'),
            'time_in_image_url' => $timeInEvidence?->time_in_face_path ? route('attendance.evidence', ['attendanceLog' => $timeInEvidence->id, 'moment' => 'time-in']) : null,
            'time_out_image_url' => $timeOutEvidence?->time_out_face_path ? route('attendance.evidence', ['attendanceLog' => $timeOutEvidence->id, 'moment' => 'time-out']) : null,
            'verification_method' => $timeInEvidence?->verification_method ?? $timeOutEvidence?->verification_method,
            'evidence_events' => $evidenceLogs
                ->map(fn ($log) => [
                    'id' => $log->id,
                    'tap_type' => $log->tap_type,
                    'tap_sequence_number' => $log->tap_sequence_number,
                    'time' => $log->tap_datetime ? date('g:i A', strtotime((string) $log->tap_datetime)) : null,
                    'room_status' => $this->tapRoomStatus($log->tap_type),
                    'validation_result' => ucfirst((string) ($log->validation_result ?? 'valid')),
                    'remarks' => $log->remarks,
                    'time_in_image_url' => $log->time_in_face_path ? route('attendance.evidence', ['attendanceLog' => $log->id, 'moment' => 'time-in']) : null,
                    'time_out_image_url' => $log->time_out_face_path ? route('attendance.evidence', ['attendanceLog' => $log->id, 'moment' => 'time-out']) : null,
                    'verification_method' => $log->verification_method,
                ])
                ->values(),
        ];
    }

    private function combinedAttendancePayloads(?Students $student, ?StudentEnrollment $enrollment = null)
    {
        if (! $student) {
            return collect();
        }

        $this->onlineAttendanceFinalizer->finalizeEnded();

        $rfidAttendance = $this->attendanceQuery($student, $enrollment)
            ->get()
            ->map(fn ($attendance) => $this->attendancePayload($attendance));

        $onlineAttendance = DB::table('online_classes')
            ->leftJoin('online_class_attendances', function ($join) use ($student) {
                $join->on('online_class_attendances.online_class_id', '=', 'online_classes.online_class_id')
                    ->where('online_class_attendances.student_id', '=', $student->student_id);
            })
            ->leftJoin('subjects', function ($join) {
                $join->on('subjects.subject_code', '=', 'online_classes.subject_code')
                    ->on('subjects.section_id', '=', 'online_classes.section_id');
            })
            ->when($enrollment,
                fn ($query) => $query->where('online_classes.academic_year_id', $enrollment->academic_year_id)->where('online_classes.section_id', $enrollment->section_id),
                fn ($query) => $query->where('online_classes.section_id', $student->section_id))
            ->where('online_classes.status', '!=', 'cancelled')
            ->whereNull('online_classes.deleted_at')
            ->orderByDesc('online_classes.scheduled_date')
            ->orderByDesc('online_classes.start_time')
            ->get([
                'online_class_attendances.online_class_attendance_id',
                'online_class_attendances.joined_at',
                'online_class_attendances.status',
                'online_class_attendances.is_late',
                'online_class_attendances.face_required',
                'online_class_attendances.face_verified',
                'online_class_attendances.face_verified_at',
                'online_classes.online_class_id',
                'online_classes.title',
                'online_classes.scheduled_date',
                'online_classes.start_time',
                'online_classes.end_time',
                'subjects.subject_name',
                'online_classes.subject_code',
            ])
            ->map(fn ($attendance) => $this->onlineAttendancePayload($attendance));

        return $rfidAttendance
            ->concat($onlineAttendance)
            ->sortByDesc(fn (array $attendance) => trim(($attendance['date'] ?? '').' '.($attendance['sort_time'] ?? '')))
            ->values();
    }

    private function onlineAttendancePayload(object $attendance): array
    {
        $joinedAt = $attendance->joined_at ? \Carbon\Carbon::parse($attendance->joined_at) : null;
        $faceVerifiedAt = $attendance->face_verified_at ? \Carbon\Carbon::parse($attendance->face_verified_at) : null;
        $hasEnded = \Carbon\Carbon::parse($attendance->scheduled_date.' '.$attendance->end_time)->isPast();
        $savedStatus = strtolower((string) $attendance->status);
        $status = in_array($savedStatus, ['present', 'late', 'absent', 'excused'], true)
            ? $savedStatus
            : ($joinedAt ? ($attendance->is_late ? 'late' : 'present') : ($hasEnded ? 'absent' : 'pending'));
        $faceStatus = $attendance->face_required
            ? ($attendance->face_verified ? 'Face verified' : 'Face required')
            : 'Face not required';

        return [
            'attendance_id' => 'online-'.$attendance->online_class_id,
            'source' => 'online',
            'date' => $attendance->scheduled_date ? \Carbon\Carbon::parse($attendance->scheduled_date)->format('Y-m-d') : $joinedAt?->format('Y-m-d'),
            'subject' => $attendance->subject_name ?? $attendance->subject_code ?? $attendance->title,
            'room' => 'Online Class',
            'time_in' => $joinedAt?->format('g:i A'),
            'time_out' => null,
            'class_time' => trim(implode(' - ', array_filter([
                $this->shortTime($attendance->start_time),
                $this->shortTime($attendance->end_time),
            ]))),
            'duration' => null,
            'status' => $status,
            'sort_time' => $joinedAt?->format('H:i:s') ?? (string) $attendance->start_time,
            'time_in_image_url' => null,
            'time_out_image_url' => null,
            'verification_method' => $joinedAt ? $faceStatus : 'Online attendance',
            'evidence_events' => [[
                'id' => 'online-'.$attendance->online_class_id,
                'tap_type' => $joinedAt ? 'Online Join' : ($hasEnded ? 'Online Absence' : 'Online Pending'),
                'tap_sequence_number' => $joinedAt ? 1 : null,
                'time' => $joinedAt?->format('g:i A'),
                'room_status' => 'Online Class',
                'validation_result' => ucfirst($status),
                'remarks' => $joinedAt
                    ? trim($faceStatus.($faceVerifiedAt ? ' at '.$faceVerifiedAt->format('g:i A') : '').($attendance->is_late ? '; joined after start time' : ''))
                    : ($hasEnded ? 'Did not join before the online class ended.' : 'Online attendance is still open.'),
                'time_in_image_url' => null,
                'time_out_image_url' => null,
                'verification_method' => $faceStatus,
            ]],
        ];
    }

    private function tapRoomStatus(?string $tapType): string
    {
        return match ($tapType) {
            'Check-in', 'Temporary Return' => 'Inside',
            'Check-out', 'Temporary Exit' => 'Outside',
            default => 'Outside',
        };
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
        $userId = (int) $request->user()?->user_id;

        return StudentPortalMessage::query()
            ->with(['sender', 'recipient', 'instructor'])
            ->where('student_id', $student->student_id)
            ->where(function ($query) use ($userId) {
                $query
                    ->where('sender_user_id', $userId)
                    ->orWhere('recipient_user_id', $userId);
            })
            ->latest();
    }

    private function messagePayload(StudentPortalMessage $message): array
    {
        return [
            'id' => $message->student_portal_message_id,
            'sender_user_id' => $message->sender_user_id,
            'recipient_user_id' => $message->recipient_user_id,
            'instructor_user_id' => $message->instructor_user_id,
            'sender' => $message->sender?->name,
            'sender_email' => $message->sender?->email,
            'sender_role' => $message->sender_role,
            'recipient' => $message->recipient?->name,
            'recipient_email' => $message->recipient?->email,
            'instructor' => $message->instructor?->name,
            'subject' => $message->subject,
            'body' => $message->body,
            'attachment_name' => $message->attachment_name,
            'attachment_url' => $message->attachment_path ? Storage::disk('public')->url($message->attachment_path) : null,
            'created_at' => $message->created_at?->toDateTimeString(),
        ];
    }

    private function letterPayload(StudentExcuseLetter $letter, ?Request $request = null): array
    {
        $role = strtolower((string) $request?->user()?->role);

        return [
            'id' => $letter->student_excuse_letter_id,
            'subject' => $letter->subject,
            'from_date' => $letter->from_date?->format('Y-m-d'),
            'to_date' => $letter->to_date?->format('Y-m-d'),
            'reason' => $letter->reason,
            'status' => $letter->status,
            'academic_year' => $letter->academicYear?->name,
            'submitted_by' => $letter->submittedBy?->name,
            'submitted_by_role' => $letter->submitted_by_role,
            'parent_signature' => $letter->parent_signature,
            'parent_approval_notes' => $letter->parent_approval_notes,
            'parent_approved_by' => $letter->parentApprovedBy?->name,
            'parent_approved_at' => $letter->parent_approved_at?->toDateTimeString(),
            'recipient_user_ids' => $letter->recipient_user_ids ?? [],
            'can_parent_approve' => $role === 'parent'
                && $letter->submitted_by_role === 'student'
                && $letter->status === 'pending_parent_approval',
            'can_download' => $letter->status !== 'pending_parent_approval',
            'attachment_name' => $letter->attachment_name,
            'attachment_url' => $letter->attachment_path
                ? route('student-parent.excuse-letters.attachment', array_filter([
                    'letter' => $letter,
                    'student_id' => $request?->input('student_id'),
                ]))
                : null,
            'download_url' => route('student-parent.excuse-letters.download', array_filter([
                'letter' => $letter,
                'student_id' => $request?->input('student_id'),
            ])),
            'created_at' => $letter->created_at?->toDateTimeString(),
        ];
    }
}
