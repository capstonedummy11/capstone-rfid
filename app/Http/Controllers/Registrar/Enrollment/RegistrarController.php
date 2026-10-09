<?php
// File purpose: RFID at face enrollment para sa Registrar.

namespace App\Http\Controllers\Registrar\Enrollment;

use App\Models\ActivityLog;
use App\Models\Instructor;
use App\Models\RegistrarEnrollmentLog;
use App\Models\Students;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class RegistrarController
{
    // @function dashboard: Ibinabalik ang Registrar/Dashboard page at data para sa request.
    // @useIn dashboard: routes/web.php:230 (dashboard)
    // Pinagsasama ang student at Instructor enrollment metrics at mga recent log.
    public function dashboard()
    {
        $people = $this->enrollmentPeople();

        return Inertia::render('Registrar/Dashboard/DashboardPage', [
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

    // @function biometricEnrollment: Ibinabalik ang Registrar/BiometricEnrollment page at data para sa request.
    // @useIn biometricEnrollment: routes/web.php:231 (biometric-enrollment)
    // Ibinabalik ang searchable student list kasama ang RFID at face completion stats.
    public function biometricEnrollment()
    {
        $people = $this->studentEnrollmentPeople();

        return Inertia::render('Registrar/BiometricEnrollment/BiometricEnrollmentPage', [
            'title' => 'Student Biometric Enrollment',
            'people' => $people,
            'stats' => $this->enrollmentStats($people),
        ]);
    }

    // @function instructorFaceEnrollment: Ibinabalik ang Registrar/InstructorFaceEnrollment page at data para sa request.
    // @useIn instructorFaceEnrollment: routes/web.php:232 (instructor-face-enrollment)
    // Ibinabalik ang Instructor list at bilang ng kulang na RFID o face records.
    public function instructorFaceEnrollment()
    {
        $people = $this->facultyPeople();

        return Inertia::render('Registrar/InstructorFaceEnrollment/InstructorFaceEnrollmentPage', [
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

    // @function enrollmentPeople: Kinukuha ang enrollment people result para sa Registrar.
    // @useIn enrollmentPeople: RegistrarController::dashboard (app/Http/Controllers/Registrar/Enrollment/RegistrarController.php)
    // Pinagsasama ang student at faculty records bago kalkulahin ang dashboard totals.
    private function enrollmentPeople()
    {
        return $this->studentEnrollmentPeople()
            ->concat($this->facultyPeople())
            ->sortBy(fn ($person) => ($person['has_face'] && $person['has_rfid'] ? 1 : 0).'-'.$person['name'])
            ->values();
    }

    // @function studentEnrollmentPeople: Kinukuha ang student enrollment people result para sa Registrar.
    // @useIn studentEnrollmentPeople: RegistrarController::biometricEnrollment (app/Http/Controllers/Registrar/Enrollment/RegistrarController.php)
    // Ginagawang enrollment cards ang student identity at face-image data.
    private function studentEnrollmentPeople()
    {
        return Students::query()
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
            ])
            ->sortBy(fn ($person) => ($person['has_face'] && $person['has_rfid'] ? 1 : 0).'-'.$person['name'])
            ->values();
    }

    // @function facultyPeople: Kinukuha ang faculty people result para sa Registrar.
    // @useIn facultyPeople: RegistrarController::instructorFaceEnrollment (app/Http/Controllers/Registrar/Enrollment/RegistrarController.php)
    // Kinukuha ang Instructor accounts na may profile para sa faculty enrollment page.
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

    // @function enrollmentStats: Kinukuha ang enrollment stats result para sa Registrar.
    // @useIn enrollmentStats: RegistrarController::dashboard (app/Http/Controllers/Registrar/Enrollment/RegistrarController.php)
    // Binibilang ang complete at incomplete RFID/face records sa ibinigay na list.
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

    // @function updateStudentRfid: Ina-update ang student rfid sa Registrar flow.
    // @useIn updateStudentRfid: routes/web.php:234 (students.rfid)
    /**
     * @feature     Student RFID Enrollment
     * @actor       Registrar
     * @flow        Dito nililink ang scanned RFID tag sa student record.
     * @uses        resources/js/pages/Registrar/BiometricEnrollment/BiometricEnrollmentPage.vue; routes/registrar.php: RegistrarController::updateStudentRfid
     * @related     Console Student lookup at attendance.
     * @disable     1) Suriin ang Student RFID Enrollment callers, pending work, at dependent screens; Needs developer check: exact shared routes at background consumers.
     * @disable     2) Magdagdag at subukan ng feature-specific server guard sa named actions; panatilihin ang shared route/method para sa ibang feature. Itago pagkatapos ang controls sa `resources/js/pages/Registrar/BiometricEnrollment/BiometricEnrollmentPage.vue`.
     * @disable     3) I-check ang affected user flow, reports, pending jobs, at historical read access; huwag burahin ang existing records/files bilang bahagi ng disable.
     * @sideEffects Ina-update ang Student RFID at Registrar enrollment audit.
     * @dependsOn   Console Student lookup at attendance.
     * @performance Needs developer check: sukatin ang request/provider/worker work bago at pagkatapos; UI hide lang ay walang nakumpirmang bilis na dagdag.
     * @dataImpact  Walang data deletion sa nakasaad na disable steps; mananatili ang records/files pero maaaring hindi mabuksan sa hidden UI.
     * @reEnable    1) Ibalik ang server guard/action. 2) Ibalik ang UI controls. 3) I-test ang actor access, dependencies, pending work, at historical data.
     * @editable    Registrar Student Biometric Enrollment: RFID assignment.
     */
    public function updateStudentRfid(Request $request, Students $student)
    {
        $validated = $request->validate([
            'rfid_tag' => [
                'required',
                'string',
                'max:255',
                Rule::unique('students', 'rfid_tag')->whereNull('deleted_at')->ignore($student->student_id, 'student_id'),
                Rule::unique('users', 'rfid_tag')->whereNull('deleted_at'),
            ],
        ]);

        $student->update(['rfid_tag' => $validated['rfid_tag']]);
        $this->logEnrollment($request, 'rfid', 'student', $student->student_id, trim($student->first_name.' '.$student->last_name), $student->student_number);

        return back()->with('success', 'Student RFID card assigned.');
    }

    // @function updateFacultyRfid: Ina-update ang faculty rfid sa Registrar flow.
    // @useIn updateFacultyRfid: routes/web.php:240 (faculty.rfid)
    /**
     * @feature     Instructor RFID and Face Enrollment
     * @actor       Registrar
     * @flow        Dito nililink ang Instructor RFID at face images sa account.
     * @uses        resources/js/pages/Registrar/InstructorFaceEnrollment/InstructorFaceEnrollmentPage.vue; routes/registrar.php: RegistrarController::updateFacultyRfid, RegistrarController::uploadFacultyFace, RegistrarController::deleteFacultyFace
     * @related     Instructor verification at Console class/movement actions.
     * @disable     1) Suriin ang Instructor RFID and Face Enrollment callers, pending work, at dependent screens; Needs developer check: exact shared routes at background consumers.
     * @disable     2) Magdagdag at subukan ng feature-specific server guard sa named actions; panatilihin ang shared route/method para sa ibang feature. Itago pagkatapos ang controls sa `resources/js/pages/Registrar/InstructorFaceEnrollment/InstructorFaceEnrollmentPage.vue`.
     * @disable     3) I-check ang affected user flow, reports, pending jobs, at historical read access; huwag burahin ang existing records/files bilang bahagi ng disable.
     * @sideEffects Ina-update ang Instructor RFID/face records at Registrar enrollment audit.
     * @dependsOn   Instructor verification at Console class/movement actions.
     * @performance Needs developer check: sukatin ang request/provider/worker work bago at pagkatapos; UI hide lang ay walang nakumpirmang bilis na dagdag.
     * @dataImpact  Walang data deletion sa nakasaad na disable steps; mananatili ang records/files pero maaaring hindi mabuksan sa hidden UI.
     * @reEnable    1) Ibalik ang server guard/action. 2) Ibalik ang UI controls. 3) I-test ang actor access, dependencies, pending work, at historical data.
     * @editable    Registrar Instructor Faces: RFID at face capture/upload/remove.
     */
    public function updateFacultyRfid(Request $request, User $user)
    {
        abort_unless(strtolower((string) $user->role) === 'instructor', 404);

        $validated = $request->validate([
            'rfid_tag' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'rfid_tag')->whereNull('deleted_at')->ignore($user->user_id, 'user_id'),
                Rule::unique('students', 'rfid_tag')->whereNull('deleted_at'),
            ],
        ]);

        $user->update(['rfid_tag' => $validated['rfid_tag']]);
        $this->logEnrollment($request, 'rfid', 'faculty', $user->user_id, trim($user->name.' '.($user->last_name ?? '')), $user->email);

        return back()->with('success', 'Faculty RFID card assigned.');
    }

    // @function uploadStudentFace: Ina-upload ang student face sa Registrar flow.
    // @useIn uploadStudentFace: routes/web.php:236 (students.face)
    /**
     * @feature     Student Face Enrollment
     * @actor       Registrar
     * @flow        Dito sine-save at tinatanggal ang enrolled student face images.
     * @uses        resources/js/pages/Registrar/BiometricEnrollment/BiometricEnrollmentPage.vue; routes/registrar.php: RegistrarController::uploadStudentFace, RegistrarController::deleteStudentFace
     * @related     Attendance face comparison at optional liveness follow-up.
     * @disable     1) Suriin ang Student Face Enrollment callers, pending work, at dependent screens; Needs developer check: exact shared routes at background consumers.
     * @disable     2) Magdagdag at subukan ng feature-specific server guard sa named actions; panatilihin ang shared route/method para sa ibang feature. Itago pagkatapos ang controls sa `resources/js/pages/Registrar/BiometricEnrollment/BiometricEnrollmentPage.vue`.
     * @disable     3) I-check ang affected user flow, reports, pending jobs, at historical read access; huwag burahin ang existing records/files bilang bahagi ng disable.
     * @sideEffects Ina-update ang Student face-image paths/files at Registrar enrollment audit.
     * @dependsOn   Attendance face comparison at optional liveness follow-up.
     * @performance Needs developer check: sukatin ang request/provider/worker work bago at pagkatapos; UI hide lang ay walang nakumpirmang bilis na dagdag.
     * @dataImpact  Walang data deletion sa nakasaad na disable steps; mananatili ang records/files pero maaaring hindi mabuksan sa hidden UI.
     * @reEnable    1) Ibalik ang server guard/action. 2) Ibalik ang UI controls. 3) I-test ang actor access, dependencies, pending work, at historical data.
     * @editable    Registrar Student Biometric Enrollment: face capture/upload/remove.
     */
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

    // @function deleteStudentFace: Tinatanggal ang student face sa Registrar flow.
    // @useIn deleteStudentFace: routes/web.php:238 (students.face.delete)
    // Binubura ang napiling stored image at ina-update ang student face list.
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

    // @function uploadFacultyFace: Ina-upload ang faculty face sa Registrar flow.
    // @useIn uploadFacultyFace: routes/web.php:242 (faculty.face)
    // Instructor account lang ang puwedeng magdagdag ng hanggang limang face images.
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

    // @function deleteFacultyFace: Tinatanggal ang faculty face sa Registrar flow.
    // @useIn deleteFacultyFace: routes/web.php:244 (faculty.face.delete)
    // Binubura ang napiling Instructor face image at nilolog ang removal.
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

    // @function dailyEnrollmentChart: Kinukuha ang daily enrollment chart result para sa Registrar.
    // @useIn dailyEnrollmentChart: RegistrarController::dashboard (app/Http/Controllers/Registrar/Enrollment/RegistrarController.php)
    // Binubuo ang 14-araw na RFID at face action counts para sa dashboard chart.
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

    // @function logEnrollment: Nilolog ang enrollment sa Registrar flow.
    // @useIn logEnrollment: RegistrarController::updateStudentRfid (app/Http/Controllers/Registrar/Enrollment/RegistrarController.php)
    // Nagtatala ng Registrar-specific log at general activity log sa bawat pagbabago.
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
