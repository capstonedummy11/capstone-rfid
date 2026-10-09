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
     * @feature     First-Login Password Setup
     * @actor       Shared / Core
     * @flow        Dito pinapalitan ang temporary password bago buksan ang ibang page.
     * @uses        resources/js/pages/Shared/Auth/FirstLoginPassword/FirstLoginPasswordPage.vue; routes/web.php: FirstLoginPasswordController::edit, FirstLoginPasswordController::update
     * @related     Protected pages ng bagong non-Console accounts.
     * @disable     1) Suriin ang First-Login Password Setup callers, pending work, at dependent screens; Needs developer check: huwag alisin ang password-setup route habang EnsurePasswordIsChanged ay nagre-redirect dito.
     * @disable     2) Magdagdag at subukan ng feature-specific server guard sa named actions; panatilihin ang shared route/method para sa ibang feature. Itago pagkatapos ang controls sa `resources/js/pages/Shared/Auth/FirstLoginPassword/FirstLoginPasswordPage.vue`.
     * @disable     3) I-check ang affected user flow, reports, pending jobs, at historical read access; huwag burahin ang existing records/files bilang bahagi ng disable.
     * @sideEffects Ina-update ang password hash at must_change_password flag; request ay naa-audit.
     * @dependsOn   Protected pages ng bagong non-Console accounts.
     * @performance Needs developer check: sukatin ang request/provider/worker work bago at pagkatapos; UI hide lang ay walang nakumpirmang bilis na dagdag.
     * @dataImpact  Walang data deletion sa nakasaad na disable steps; mananatili ang records/files pero maaaring hindi mabuksan sa hidden UI.
     * @reEnable    1) Ibalik ang server guard/action. 2) Ibalik ang UI controls. 3) I-test ang actor access, dependencies, pending work, at historical data.
     * @editable    First-login page: bagong private password; requirement policy ay code/config.
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
