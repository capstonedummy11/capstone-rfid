<?php

namespace App\Http\Controllers\Shared\Settings\Profile;

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

// Used by authenticated users across roles for self-service profile settings.
class ProfileController extends Controller
{
    // @function edit: Ibinabalik ang settings/Profile page at data para sa request.
    // @useIn edit: routes/settings.php:12 (profile.edit)
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Shared/Settings/Profile/ProfilePage', [
            'title' => 'Profile Settings',
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    // @function update: Pinoproseso ang pagbabago sa Profile record.
    // @useIn update: routes/settings.php:13 (profile.update)
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

    // @function destroy: Pinoproseso ang pagtanggal ng Profile record.
    // @useIn destroy: routes/settings.php:17 (profile.destroy)
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
