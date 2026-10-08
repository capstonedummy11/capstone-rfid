<?php

namespace App\Http\Controllers\Shared\Settings\Password;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

// Used by authenticated, verified users; the settings routes do not restrict access by role.
class PasswordController extends Controller
{
    // @function edit: Ibinabalik ang settings/Password page at data para sa request.
    // @useIn edit: routes/settings.php:19 (user-password.edit)
    /**
     * Show the user's password settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('Shared/Settings/Password/PasswordPage', [
            'title' => 'Password Settings',
        ]);
    }

    // @function update: Pinoproseso ang pagbabago sa Password record.
    // @useIn update: routes/settings.php:21 (user-password.update)
    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        return back();
    }
}
