<?php

namespace App\Http\Controllers\Shared\Auth\FirstLoginPassword;

use App\Http\Controllers\Controller;

use App\Concerns\PasswordValidationRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

// Used by any authenticated role whose account is required to replace its temporary password.
class FirstLoginPasswordController extends Controller
{
    use PasswordValidationRules;

    // @function edit: Ibinabalik ang Auth/FirstLoginPassword page at data para sa request.
    // @useIn edit: routes/web.php:101 (password.first-login)
    /**
     * @feature   First-Login Password Setup
     *
     * @actor     Shared / Core
     *
     * @flow      Dito pinapalitan ang temporary password bago buksan ang ibang page.
     *
     * @uses      resources/js/pages/Shared/Auth/FirstLoginPassword/FirstLoginPasswordPage.vue; routes/web.php: FirstLoginPasswordController::edit, FirstLoginPasswordController::update
     *
     * @related   Authentication, Attendance, Reports
     *
     * @disable   1) I-comment out ang routes/web.php: FirstLoginPasswordController::edit, FirstLoginPasswordController::update.
     * @disable   2) Itago ang action sa resources/js/pages/Shared/Auth/FirstLoginPassword/FirstLoginPasswordPage.vue; kung may menu link, alisin ito sa resources/js/layouts/AuthNavbar.vue.
     * @disable   3) Ihinto ang app/Http/Controllers/Shared/Auth/FirstLoginPassword/FirstLoginPasswordController.php: FirstLoginPasswordController::edit matapos alisin ang routes. Side effect: mawawala ang first-login password setup.
     */
    public function edit(Request $request)
    {
        abort_if(strtolower((string) $request->user()?->role) === 'console', 403);

        if (! $request->user()->must_change_password) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Shared/Auth/FirstLoginPassword/FirstLoginPasswordPage', [
            'title' => 'Change Temporary Password',
        ]);
    }

    // @function update: Pinoproseso ang pagbabago sa First Login Password record.
    // @useIn update: routes/web.php:103 (password.first-login.update)
    public function update(Request $request)
    {
        abort_if(strtolower((string) $request->user()?->role) === 'console', 403);

        $validated = Validator::make($request->all(), [
            'password' => $this->passwordRules(),
        ], $this->passwordValidationMessages())->validate();

        if (Hash::check($validated['password'], (string) $request->user()->password)) {
            throw ValidationException::withMessages([
                'password' => 'Choose a password different from your temporary password.',
            ]);
        }

        $request->user()->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
        ])->save();

        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Your new password is active.');
    }
}
