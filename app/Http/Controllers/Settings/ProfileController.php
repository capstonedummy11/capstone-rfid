<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Services\ProfilePhotoService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'title' => 'Profile Settings',
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, ProfilePhotoService $profilePhotos): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $user->fill(collect($validated)->except([
            'profile_photo',
            'remove_profile_photo',
        ])->all());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();
        $profilePhotos->update(
            $user,
            $request->file('profile_photo'),
            $request->boolean('remove_profile_photo'),
        );

        return to_route('profile.edit')->with('success', 'Profile updated.');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();
        $profilePhotoPath = $user->profile_photo_path;

        Auth::logout();

        $user->forceDelete();

        if ($profilePhotoPath) {
            Storage::disk('public')->delete($profilePhotoPath);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
