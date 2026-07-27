<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController
{
    public function register(Request $request)
    {
        $register = $request->validate([
            'name' => ['required', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', 'min:6'],
        ]);

        $register['role'] = 'student';

        $user = User::create($register);

        Auth::login($user);

        return redirect()->route('dashboard')->with('success', 'Registered successfully!');
    }
}
