<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController
{
    public function register(Request $request) {

        //Validate
        $register = $request->validate([
            'name' => ['required', 'max:255'], 
            'email' => ['required', 'max:255', 'unique:users'], 
            'password' => ['required', 'confirmed', 'min:6'],
            'role' => ['required', 'in:admin,instructor'],
        ]);

        //Register
        $user = User::create($register);

        //Login
        Auth::login($user);

        //Redirect
        return response()->json([
            'message' => 'Registered successfully',
            'user' => $user,
        ]);
    }
}
