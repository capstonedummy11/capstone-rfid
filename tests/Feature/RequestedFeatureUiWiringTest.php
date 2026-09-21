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

test('instructor password reset uses application confirmation and success modals', function () {
    $instructors = file_get_contents(resource_path('js/pages/Auth/Admin/Instructors.vue'));

    expect($instructors)
        ->toContain('Reset Instructor password?')
        ->toContain('aria-label="Reset password"')
        ->toContain('aria-label="Reset security questions"')
        ->toContain('role="tooltip"')
        ->toContain('aria-modal="true"')
        ->toContain('confirmResetInstructorPassword')
        ->toContain('confirmResetInstructorSecurityQuestions')
        ->toContain('Password reset successfully')
        ->toContain('Security questions reset')
        ->not->toContain('if (!confirm(`Reset ${name}\'s password');
});

test('schedule time inputs and grid use half hour intervals', function () {
    $schedules = file_get_contents(resource_path('js/pages/Auth/Admin/Schedules.vue'));

    expect($schedules)
        ->toContain('step="1800"')
        ->toContain('const SLOT_MINUTES = 30;')
        ->toContain('Start time must use a 30-minute interval.')
        ->toContain('End time must use a 30-minute interval.')
        ->not->toContain('step="900"');
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
