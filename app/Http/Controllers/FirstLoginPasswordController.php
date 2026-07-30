<?php

namespace App\Http\Controllers;

use App\Concerns\PasswordValidationRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class FirstLoginPasswordController extends Controller
{
    use PasswordValidationRules;

    public function edit(Request $request)
    {
        abort_if(strtolower((string) $request->user()?->role) === 'console', 403);

        if (! $request->user()->must_change_password) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/FirstLoginPassword');
    }

    public function update(Request $request)
    {
        abort_if(strtolower((string) $request->user()?->role) === 'console', 403);

        $validated = Validator::make($request->all(), [
            'password' => $this->passwordRules(),
        ])->validate();

        abort_if(Hash::check($validated['password'], (string) $request->user()->password), 422, 'Choose a password different from your temporary password.');

        $request->user()->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
        ])->save();

        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Your new password is active.');
    }
}
