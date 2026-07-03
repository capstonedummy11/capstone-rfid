<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Instructor;
use App\Models\Section;
use App\Models\Strand;
use App\Models\Students;
use App\Services\CompreFaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $this->logActivity('create', 'students', 'Created student ' . $student->student_number);

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
            'student_number' => 'required|string|max:255|unique:students,student_number,' . $id . ',student_id',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:students,email,' . $id . ',student_id',
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|in:male,female',
            'strand_id' => 'required|exists:strands,strand_id',
            'section_id' => 'required|exists:sections,section_id',
            'year_level' => 'required|integer|in:11,12',
            'semester' => 'required|string|max:50',
            'school_year' => 'required|string|max:20',
            'rfid_tag' => 'nullable|string|max:255|unique:students,rfid_tag,' . $id . ',student_id',
            'status' => 'required|in:active,inactive,graduated,dropped',
        ]);

        $student->update($validated);
        $this->logActivity('update', 'students', 'Updated student ' . $student->student_number);

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
        (new CompreFaceService())->enrollFace($student->student_number, $absolutePath);

        $this->logActivity('update', 'students', 'Added face image for student ' . $student->student_number);

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

        if (!isset($currentImages[(int) $index])) {
            return response()->json(['ok' => false, 'message' => 'Image not found.'], 404);
        }

        Storage::disk('public')->delete($currentImages[(int) $index]);
        array_splice($currentImages, (int) $index, 1);
        $student->update(['face_images' => array_values($currentImages)]);

        $this->logActivity('update', 'students', 'Removed face image for student ' . $student->student_number);

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
        (new CompreFaceService())->deleteSubject($student->student_number);

        $student->delete();

        $this->logActivity('delete', 'students', 'Deleted student ' . $studentNumber);

        return back()->with('success', 'Student deleted successfully.');
    }

    // Student/Parent Portal Methods
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $attendance = [];
        $borrowing = [];

        if ($user->role === 'student') {
            $student = Students::where('email', $user->email)->first();
            if ($student) {
                $attendance = $student->attendances()->with('subjectRecord')->orderBy('date', 'desc')->take(5)->get();
                $borrowing = $student->borrowings()->orderBy('borrowed_at', 'desc')->get();
            }
        }

        return Inertia::render('StudentParent/Dashboard', [
            'title' => 'Dashboard',
            'attendance' => $attendance,
            'borrowing' => $borrowing,
        ]);
    }

    public function showProfile(Request $request)
    {
        $user = $request->user();

        return Inertia::render('StudentParent/Profile', [
            'title' => 'My Profile',
            'user' => $user,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'in:Male,Female,Other'],
        ]);

        $user->update($validated);

        return redirect()->route('student-parent.profile.show')->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'password' => bcrypt($validated['password']),
        ]);

        return redirect()->route('student-parent.profile.show')->with('success', 'Password updated successfully.');
    }

    public function attendance(Request $request)
    {
        $user = $request->user();
        $attendance = [];

        if ($user->role === 'student') {
            $student = Students::where('email', $user->email)->first();
            if ($student) {
                $attendance = $student->attendances()->with('subjectRecord')->orderBy('date', 'desc')->get();
            }
        }

        return Inertia::render('StudentParent/Attendance', [
            'title' => 'Attendance',
            'attendance' => $attendance,
        ]);
    }

    public function excuseLetters(Request $request)
    {
        $user = $request->user();
        $excuseLetters = collect();

        // TODO: Implement excuse letters model and retrieval
        // For now, return empty collection

        return Inertia::render('StudentParent/ExcuseLetters', [
            'title' => 'Excuse Letters',
            'excuseLetters' => $excuseLetters,
        ]);
    }

    public function storeExcuseLetter(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'reason' => ['required', 'string'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,doc,docx,jpg,jpeg,png'],
        ]);

        // TODO: Store excuse letter in database
        // For now, just return success

        return redirect()->route('student-parent.excuse-letters.index')->with('success', 'Excuse letter submitted successfully.');
    }

    public function messages(Request $request)
    {
        $user = $request->user();
        $messages = collect();

        // TODO: Implement messages retrieval for students/parents
        // For now, return empty collection

        return Inertia::render('StudentParent/Messages', [
            'title' => 'Messages',
            'messages' => $messages,
        ]);
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
}
