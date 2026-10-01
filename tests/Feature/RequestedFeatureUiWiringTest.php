<?php

test('standalone rfid navigation is hidden and registrar navigation uses student biometric enrollment', function () {
    $navbar = file_get_contents(resource_path('js/layouts/AuthNavbar.vue'));
    $studentEnrollment = file_get_contents(resource_path('js/pages/Registrar/BiometricEnrollment.vue'));

    expect($navbar)
        ->toContain("text: 'Student Biometric Enrollment'")
        ->not->toMatch('/^[ \t]*text:[ \t]*[\'"]RFID[\'"]/m')
        ->and($studentEnrollment)
        ->toContain('Student Biometric Enrollment');
});

test('registrar face enrollment panels follow refreshed server props without a browser refresh', function () {
    $studentEnrollment = file_get_contents(resource_path('js/pages/Registrar/BiometricEnrollment.vue'));
    $instructorEnrollment = file_get_contents(resource_path('js/pages/Registrar/InstructorFaceEnrollment.vue'));

    expect($studentEnrollment)
        ->toContain('const selectedPersonKey = ref(null)')
        ->toContain('props.people.find(')
        ->toContain('`${person.type}-${person.id}` === selectedPersonKey.value')
        ->toContain('selectedPersonKey.value = `${person.type}-${person.id}`')
        ->and($instructorEnrollment)
        ->toContain('const selectedPersonId = ref(null)')
        ->toContain('props.people.find((person) => person.id === selectedPersonId.value)')
        ->toContain('selectedPersonId.value = person.id');
});

test('face liveness requests use the laravel xsrf cookie instead of a missing meta token', function () {
    $faceLiveness = file_get_contents(resource_path('js/lib/faceLiveness.tsx'));

    expect($faceLiveness)
        ->toContain("cookie.startsWith('XSRF-TOKEN=')")
        ->toContain('decodeURIComponent(encodedToken)')
        ->toContain("'X-XSRF-TOKEN': xsrfToken()")
        ->not->toContain('meta[name="csrf-token"]')
        ->not->toContain("'X-CSRF-TOKEN': csrfToken()");
});

test('instructor liveness displays test diagnostics without enabling them for other flows', function () {
    $faceLiveness = file_get_contents(resource_path('js/lib/faceLiveness.tsx'));
    $instructorVerify = file_get_contents(resource_path('js/pages/Auth/InstructorVerify.vue'));
    $attendancePanel = file_get_contents(resource_path('js/pages/AttendanceControlPanel.vue'));
    $onlineClasses = file_get_contents(resource_path('js/pages/StudentParent/OnlineClasses.vue'));

    expect($faceLiveness)
        ->toContain('diagnosticMode = false')
        ->toContain('Test details:')
        ->toContain('reference_image_received')
        ->and($instructorVerify)
        ->toContain('diagnosticMode: true')
        ->and($attendancePanel)
        ->not->toContain('diagnosticMode: true')
        ->and($onlineClasses)
        ->not->toContain('diagnosticMode: true');
});

test('large admin relationship inputs use searchable autosuggestion controls', function () {
    $subjects = file_get_contents(resource_path('js/pages/Auth/Admin/Subjects.vue'));
    $schedules = file_get_contents(resource_path('js/pages/Auth/Admin/Schedules.vue'));
    $onlineClasses = file_get_contents(resource_path('js/pages/Auth/Admin/OnlineClasses.vue'));

    expect($subjects)
        ->toContain("import SearchableSelect from '@/components/SearchableSelect.vue'")
        ->toContain('<SearchableSelect')
        ->and($schedules)
        ->toContain("import SearchableSelect from '@/components/SearchableSelect.vue'")
        ->toContain('<SearchableSelect')
        ->and($onlineClasses)
        ->toContain("import SearchableSelect from '@/components/SearchableSelect.vue'")
        ->toContain('<SearchableSelect');
});

test('public student and parent recovery page does not expose staff login', function () {
    $forgotPassword = file_get_contents(resource_path('js/pages/Auth/ForgotPassword.vue'));

    expect($forgotPassword)
        ->toContain('Student / Parent login')
        ->not->toContain("route('staff.login')")
        ->not->toContain('>Staff login<');
});

test('password change and reset forms explain the twelve character minimum', function () {
    $passwordPolicy = file_get_contents(resource_path('js/lib/passwordPolicy.ts'));
    $resetPassword = file_get_contents(resource_path('js/pages/Auth/ResetPassword.vue'));
    $firstLoginPassword = file_get_contents(resource_path('js/pages/Auth/FirstLoginPassword.vue'));
    $portalProfile = file_get_contents(resource_path('js/pages/StudentParent/Profile.vue'));

    expect($passwordPolicy)
        ->toContain('MIN_PASSWORD_LENGTH = 12')
        ->toContain('Password must be at least 12 characters.')
        ->toContain('Password must be at least 12 characters long.')
        ->and($resetPassword)
        ->toContain(':minlength="MIN_PASSWORD_LENGTH"')
        ->toContain('PASSWORD_LENGTH_HELPER')
        ->toContain('PASSWORD_LENGTH_ERROR')
        ->and($firstLoginPassword)
        ->toContain(':minlength="MIN_PASSWORD_LENGTH"')
        ->toContain('evaluatePasswordRequirements')
        ->toContain('Password strength')
        ->toContain('New password must contain:')
        ->toContain('PASSWORD_LENGTH_ERROR')
        ->and($portalProfile)
        ->toContain(':minlength="MIN_PASSWORD_LENGTH"')
        ->toContain('PASSWORD_LENGTH_HELPER')
        ->toContain('PASSWORD_LENGTH_ERROR');
});

test('password change and reset actions prevent duplicate rapid submissions', function () {
    $resetPassword = file_get_contents(resource_path('js/pages/Auth/ResetPassword.vue'));
    $firstLoginPassword = file_get_contents(resource_path('js/pages/Auth/FirstLoginPassword.vue'));
    $portalProfile = file_get_contents(resource_path('js/pages/StudentParent/Profile.vue'));

    expect($resetPassword)
        ->toContain('if (isSubmitting.value) return;')
        ->toContain('isSubmitting.value = true;')
        ->toContain('onFinish: () => {')
        ->toContain('isSubmitting.value = false;')
        ->toContain(':disabled="isSubmitting || form.processing"')
        ->and($firstLoginPassword)
        ->toContain('if (isSubmitting.value) return;')
        ->toContain('isSubmitting.value = true;')
        ->toContain('onFinish: () => {')
        ->toContain('isSubmitting.value = false;')
        ->toContain('isSubmitting ||')
        ->toContain('form.processing ||')
        ->toContain('!passwordRequirementsMet')
        ->and($portalProfile)
        ->toContain('if (isPasswordSubmitting.value) return;')
        ->toContain('isPasswordSubmitting.value = true;')
        ->toContain('onFinish: () => {')
        ->toContain('isPasswordSubmitting.value = false;')
        ->toContain('isPasswordSubmitting || passwordForm.processing');
});

test('portal login forms remember email without requesting persistent authentication', function () {
    $studentParentLogin = file_get_contents(resource_path('js/pages/Auth/StudentParentLogin.vue'));
    $staffLogin = file_get_contents(resource_path('js/pages/Auth/StaffLogin.vue'));
    $savedProfiles = file_get_contents(resource_path('js/composables/useSavedStudentParentProfiles.js'));

    expect($studentParentLogin)
        ->toContain('Remember my email')
        ->toContain('Your password is never saved.')
        ->toContain('v-model="saveOnDevice"')
        ->not->toContain('remember: false')
        ->and($staffLogin)
        ->toContain('Remember my email')
        ->toContain('Your password is never saved.')
        ->toContain('v-model="saveOnDevice"')
        ->not->toContain('remember: false')
        ->and($savedProfiles)
        ->toContain('email,')
        ->not->toMatch('/^[ \t]*(password|token):/m');
});

test('instructor password reset uses application confirmation and success modals', function () {
    $instructors = file_get_contents(resource_path('js/pages/Auth/Admin/Instructors.vue'));

    expect($instructors)
        ->toContain('Reset Instructor password?')
        ->toContain('aria-label="Reset password"')
        ->toContain('aria-label="Reset security questions"')
        ->toContain('role="tooltip"')
        ->toContain('aria-modal="true"')
        ->toContain('confirmResetInstructorPassword')
        ->toContain('defaultInstructorPassword')
        ->toMatch("/\\.replace\\(\/\\\\s\\+\/g, ''\\)\\s*\\.toLowerCase\\(\\)/")
        ->toContain('resetConfirmationPassword')
        ->toContain('resetSuccessPassword')
        ->toContain('confirmResetInstructorSecurityQuestions')
        ->toContain('Password reset successfully')
        ->toContain('Security questions reset')
        ->not->toContain('if (!confirm(`Reset ${name}\'s password');
});

test('student default password preview removes spaces and uses lowercase', function () {
    $students = file_get_contents(resource_path('js/pages/Auth/Admin/Students.vue'));

    expect($students)
        ->toContain('const defaultStudentPassword')
        ->toContain(').toLowerCase()');
});

test('clinic and registrar rows expose an application password reset flow', function () {
    $users = file_get_contents(resource_path('js/pages/Auth/Admin/UserManagement.vue'));

    expect($users)
        ->toContain('user.can_reset_password')
        ->toContain("route('admin.users.password.reset-default', user.id)")
        ->toContain('Reset {{ roleLabel(passwordResetUser.role) }} password?')
        ->toContain('passwordResetForm.processing')
        ->toContain('passwordResetSuccess')
        ->toContain('v-model="form.password_confirmation"')
        ->toContain(":type=\"showPassword ? 'text' : 'password'\"")
        ->toContain('showPasswordConfirmation')
        ->toContain('The password confirmation does not match.')
        ->not->toContain('v-model="form.is_root_admin"');
});

test('schedule create and update time inputs use quarter hour intervals', function () {
    $schedules = file_get_contents(resource_path('js/pages/Auth/Admin/Schedules.vue'));

    expect($schedules)
        ->toContain('step="900"')
        ->toContain('const SLOT_MINUTES = 15;')
        ->toContain('Start time must use a 15-minute interval.')
        ->toContain('End time must use a 15-minute interval.')
        ->not->toContain('step="1800"');
});

test('schedule landing state is a laboratory and subject dashboard', function () {
    $schedules = file_get_contents(resource_path('js/pages/Auth/Admin/Schedules.vue'));

    expect($schedules)
        ->toContain('Schedule Dashboard')
        ->toContain('Total laboratories')
        ->toContain('Active laboratories')
        ->toContain('Labs with schedules')
        ->toContain('Scheduled subjects')
        ->toContain('Laboratory schedule summary')
        ->toContain('const laboratoryDashboard = computed')
        ->toContain('const dashboardStats = computed')
        ->not->toContain('All Rooms');
});

test('excuse letter defaults an empty end date to the selected start date', function () {
    $excuseLetters = file_get_contents(resource_path('js/pages/StudentParent/ExcuseLetters.vue'));

    expect($excuseLetters)
        ->toContain("import { computed, reactive, ref, watch } from 'vue'")
        ->toContain('if (startDate && !form.to_date)')
        ->toContain('form.to_date = startDate;')
        ->toContain('const validateLetterForm = () =>')
        ->toContain("setError('subject', 'Enter the excuse-letter subject.')")
        ->toContain("setError('attachment', 'The attachment must not exceed 5 MB.')")
        ->toContain('novalidate');
});

test('frontend workflows use application modals instead of native browser dialogs', function () {
    $nativeDialogUsages = [];
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('js')),
    );

    foreach ($files as $file) {
        if (! $file->isFile() || ! in_array($file->getExtension(), ['js', 'ts', 'tsx', 'vue'], true)) {
            continue;
        }

        $source = file_get_contents($file->getPathname());

        if (preg_match('/(?<![A-Za-z0-9_])(alert|confirm|prompt)\s*\(/', $source) === 1) {
            $nativeDialogUsages[] = $file->getPathname();
        }
    }

    $feedbackModal = file_get_contents(resource_path('js/lib/feedbackModal.ts'));

    expect($nativeDialogUsages)
        ->toBe([])
        ->and($feedbackModal)
        ->toContain("from 'sweetalert2'")
        ->toContain('showCancelButton: true')
        ->toContain('focusCancel: true');
});

test('about developer easter egg is temporary and removes the portrait border', function () {
    $about = file_get_contents(resource_path('js/pages/About.vue'));

    expect($about)
        ->toContain('focus-visible:ring-4')
        ->toContain("developer.revealed\n                                            ? 'border-0'")
        ->toContain('const EASTER_EGG_DURATION_MS = 15_000;')
        ->toContain('window.setTimeout(() => {')
        ->toContain('developer.image = originalImage;')
        ->toContain('developer.clicks = 0;')
        ->toContain('}, EASTER_EGG_DURATION_MS);');
});

test('public navigation opens the about page and centers the team photo faces', function () {
    $about = file_get_contents(resource_path('js/pages/About.vue'));
    $landing = file_get_contents(resource_path('js/pages/ReusableLandingIndex.vue'));
    $studentLogin = file_get_contents(resource_path('js/pages/Auth/StudentParentLogin.vue'));
    $layout = file_get_contents(resource_path('js/layouts/Layout.vue'));

    expect($about)
        ->toContain('object-[center_58%]')
        ->toContain('md:object-[center_60%]')
        ->and($landing)->not->toContain('href="#about_us"')
        ->and($landing)->toContain('href="/about"')
        ->and($studentLogin)->not->toContain('href="#about_us"')
        ->and($studentLogin)->toContain('href="/about"')
        ->and($layout)->not->toContain('href="#about_us"')
        ->and($layout)->toContain(':href="route(\'about\')"');
});

test('landing portal showcase is a student feature carousel', function () {
    $landing = file_get_contents(resource_path('js/pages/Auth/StudentParentLogin.vue'));

    expect($landing)
        ->toContain('Built for student access')
        ->not->toContain("title: 'Parent Access'")
        ->toContain("title: 'Attendance Records'")
        ->toContain("title: 'Online Classes'")
        ->toContain("title: 'Excuse Letters'")
        ->toContain("title: 'Messages'")
        ->toContain("title: 'Notifications'")
        ->toContain("title: 'Profile & Security'")
        ->toContain('aria-roledescription="carousel"')
        ->toContain('@keydown.left.prevent="moveShowcase(-1)"')
        ->toContain('@keydown.right.prevent="moveShowcase(1)"');
});

test('user management exposes protected root ownership workflows', function () {
    $page = file_get_contents(resource_path('js/pages/Auth/Admin/UserManagement.vue'));
    $panel = file_get_contents(resource_path('js/components/Admin/RootOwnershipPanel.vue'));

    expect($page)->toContain('RootOwnershipPanel')
        ->and($panel)->toContain('Root Admin ownership')
        ->toContain('Continue securely')
        ->toContain('two_factor_code')
        ->toContain('Emergency override')
        ->toContain("decideOverride('approve')")
        ->toContain('filterAudit')
        ->toContain('Actor email')
        ->toContain('Ownership audit log');
});

test('admin login verification shows the required otp controls', function () {
    $page = file_get_contents(resource_path('js/pages/Auth/AdminLoginVerification.vue'));

    expect($page)
        ->toContain('Admin security checkpoint')
        ->toContain('autocomplete="one-time-code"')
        ->toContain("route('admin.login-verification.verify')")
        ->toContain("route('admin.login-verification.resend')")
        ->toContain('Resend code')
        ->toContain("router.post(route('logout'))");
});
