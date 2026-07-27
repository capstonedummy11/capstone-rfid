<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Instructor;
use App\Models\RegistrarEnrollmentLog;
use App\Models\Students;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class RegistrarController
{
    public function dashboard()
    {
        $people = $this->enrollmentPeople();

        return Inertia::render('Registrar/Dashboard', [
            'title' => 'Registrar Dashboard',
            'stats' => $this->enrollmentStats($people),
            'charts' => [
                'dailyEnrollment' => $this->dailyEnrollmentChart(),
            ],
            'recentLogs' => RegistrarEnrollmentLog::query()
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (RegistrarEnrollmentLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'person_type' => $log->person_type,
                    'person_name' => $log->person_name,
                    'identifier' => $log->identifier,
                    'date' => $log->created_at?->format('M d, Y'),
                    'time' => $log->created_at?->format('g:i A'),
                ]),
        ]);
    }

    public function biometricEnrollment()
    {
        $people = $this->enrollmentPeople();

        return Inertia::render('Registrar/BiometricEnrollment', [
            'title' => 'Biometric Enrollment',
            'people' => $people,
            'stats' => $this->enrollmentStats($people),
        ]);
    }

    public function instructorFaceEnrollment()
    {
        $people = $this->facultyPeople();

        return Inertia::render('Registrar/InstructorFaceEnrollment', [
            'title' => 'Instructor Biometric Enrollment',
            'people' => $people,
            'stats' => [
                'total' => $people->count(),
                'missing_face' => $people->where('has_face', false)->count(),
                'missing_rfid' => $people->where('has_rfid', false)->count(),
                'complete' => $people->filter(fn ($person) => $person['has_face'] && $person['has_rfid'])->count(),
            ],
        ]);
    }

    private function enrollmentPeople()
    {
        $students = Students::query()
            ->with(['section', 'strand'])
            ->get()
            ->map(fn (Students $student) => [
                'type' => 'student',
                'id' => $student->student_id,
                'number' => $student->student_number,
                'name' => trim($student->first_name.' '.$student->last_name),
                'email' => $student->email,
                'section' => $student->section?->section_name,
                'strand' => $student->strand?->strand_code,
                'rfid_tag' => $student->rfid_tag,
                'has_rfid' => filled($student->rfid_tag),
                'has_face' => count(array_filter($student->face_images ?? [])) > 0,
                'face_count' => count(array_filter($student->face_images ?? [])),
                'face_images' => array_values(array_filter($student->face_images ?? [])),
            ]);

        $faculty = $this->facultyPeople();

        $people = $students
            ->concat($faculty)
            ->sortBy(fn ($person) => ($person['has_face'] && $person['has_rfid'] ? 1 : 0).'-'.$person['name'])
            ->values();

        return $people;
    }

    private function facultyPeople()
    {
        return Instructor::query()
            ->with(['user', 'strand'])
            ->get()
            ->filter(fn (Instructor $instructor) => $instructor->user)
            ->map(function (Instructor $instructor) {
                $user = $instructor->user;

                return [
                    'type' => 'faculty',
                    'id' => $user->user_id,
                    'number' => $instructor->instructor_number,
                    'name' => trim($user->name.' '.($user->last_name ?? '')),
                    'email' => $user->email,
                    'section' => null,
                    'strand' => $instructor->strand?->strand_code,
                    'rfid_tag' => $user->rfid_tag,
                    'has_rfid' => filled($user->rfid_tag),
                    'has_face' => count(array_filter($user->face_images ?? [])) > 0,
                    'face_count' => count(array_filter($user->face_images ?? [])),
                    'face_images' => array_values(array_filter($user->face_images ?? [])),
                ];
            })
            ->sortBy(fn ($person) => ($person['has_face'] ? 1 : 0).'-'.$person['name'])
            ->values();
    }

    private function enrollmentStats($people): array
    {
        return [
            'total' => $people->count(),
            'students' => $people->where('type', 'student')->count(),
            'faculty' => $people->where('type', 'faculty')->count(),
            'missing_face' => $people->where('has_face', false)->count(),
            'missing_rfid' => $people->where('has_rfid', false)->count(),
            'complete' => $people->filter(fn ($person) => $person['has_face'] && $person['has_rfid'])->count(),
        ];
    }

    public function updateStudentRfid(Request $request, Students $student)
    {
        $validated = $request->validate([
            'rfid_tag' => ['required', 'string', 'max:255', 'unique:students,rfid_tag,'.$student->student_id.',student_id', 'unique:users,rfid_tag'],
        ]);

        $student->update(['rfid_tag' => $validated['rfid_tag']]);
        $this->logEnrollment($request, 'rfid', 'student', $student->student_id, trim($student->first_name.' '.$student->last_name), $student->student_number);

        return back()->with('success', 'Student RFID card assigned.');
    }

    public function updateFacultyRfid(Request $request, User $user)
    {
        abort_unless(strtolower((string) $user->role) === 'instructor', 404);

        $validated = $request->validate([
            'rfid_tag' => ['required', 'string', 'max:255', 'unique:users,rfid_tag,'.$user->user_id.',user_id', 'unique:students,rfid_tag'],
        ]);

        $user->update(['rfid_tag' => $validated['rfid_tag']]);
        $this->logEnrollment($request, 'rfid', 'faculty', $user->user_id, trim($user->name.' '.($user->last_name ?? '')), $user->email);

        return back()->with('success', 'Faculty RFID card assigned.');
    }

    public function uploadStudentFace(Request $request, Students $student)
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
        ]);

        $images = array_values($student->face_images ?? []);
        if (count($images) >= 5) {
            throw ValidationException::withMessages([
                'image' => 'Maximum 5 face images allowed per student.',
            ]);
        }

        $images[] = $request->file('image')->store('student_faces', 'public');
        $student->update(['face_images' => $images]);
        $this->logEnrollment($request, 'face', 'student', $student->student_id, trim($student->first_name.' '.$student->last_name), $student->student_number);

        return back()->with('success', 'Student face image submitted.');
    }

    public function deleteStudentFace(Request $request, Students $student, int $index)
    {
        $images = array_values($student->face_images ?? []);

        if (! isset($images[$index])) {
            throw ValidationException::withMessages([
                'image' => 'Selected face image was not found.',
            ]);
        }

        Storage::disk('public')->delete($images[$index]);
        array_splice($images, $index, 1);
        $student->update(['face_images' => array_values($images)]);
        $this->logEnrollment($request, 'face_removed', 'student', $student->student_id, trim($student->first_name.' '.$student->last_name), $student->student_number);

        return back()->with('success', 'Student face image removed.');
    }

    public function uploadFacultyFace(Request $request, User $user)
    {
        abort_unless(strtolower((string) $user->role) === 'instructor', 404);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
        ]);

        $images = array_values($user->face_images ?? []);
        if (count($images) >= 5) {
            throw ValidationException::withMessages([
                'image' => 'Maximum 5 face images allowed per faculty member.',
            ]);
        }

        $images[] = $request->file('image')->store('instructor_faces', 'public');
        $user->update(['face_images' => $images]);
        $this->logEnrollment($request, 'face', 'faculty', $user->user_id, trim($user->name.' '.($user->last_name ?? '')), $user->email);

        return back()->with('success', 'Faculty face image submitted.');
    }

    public function deleteFacultyFace(Request $request, User $user, int $index)
    {
        abort_unless(strtolower((string) $user->role) === 'instructor', 404);

        $images = array_values($user->face_images ?? []);

        if (! isset($images[$index])) {
            throw ValidationException::withMessages([
                'image' => 'Selected face image was not found.',
            ]);
        }

        Storage::disk('public')->delete($images[$index]);
        array_splice($images, $index, 1);
        $user->update(['face_images' => array_values($images)]);
        $this->logEnrollment($request, 'face_removed', 'faculty', $user->user_id, trim($user->name.' '.($user->last_name ?? '')), $user->email);

        return back()->with('success', 'Faculty face image removed.');
    }

    private function dailyEnrollmentChart(): array
    {
        $start = now()->subDays(13)->startOfDay();
        $logs = RegistrarEnrollmentLog::query()
            ->where('created_at', '>=', $start)
            ->get()
            ->groupBy(fn (RegistrarEnrollmentLog $log) => $log->created_at?->toDateString());

        return collect(range(0, 13))
            ->map(function (int $offset) use ($start, $logs) {
                $date = $start->copy()->addDays($offset);
                $dayLogs = $logs->get($date->toDateString(), collect());

                return [
                    'date' => $date->toDateString(),
                    'label' => $date->format('M d'),
                    'face' => $dayLogs->whereIn('action', ['face', 'face_removed'])->count(),
                    'rfid' => $dayLogs->where('action', 'rfid')->count(),
                ];
            })
            ->values()
            ->all();
    }

    private function logEnrollment(Request $request, string $action, string $personType, int $personId, string $personName, ?string $identifier): void
    {
        RegistrarEnrollmentLog::query()->create([
            'registrar_user_id' => $request->user()?->user_id,
            'action' => $action,
            'person_type' => $personType,
            'person_id' => $personId,
            'person_name' => $personName,
            'identifier' => $identifier,
        ]);

        ActivityLog::query()->create([
            'user_id' => $request->user()?->user_id,
            'action' => match ($action) {
                'rfid' => 'update',
                'face_removed' => 'delete',
                default => 'upload',
            },
            'table_name' => $personType === 'student' ? 'students' : 'users',
            'description' => 'Registrar '.str_replace('_', ' ', $action).' enrollment for '.$personType.' '.$personName.' ('.$identifier.').',
        ]);
    }
}
