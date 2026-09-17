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
