<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProfilePhotoService
{
    // @function update: Pinoproseso ang pagbabago sa Profile Photo record.
    // @useIn update: app/Http/Controllers/Shared/Students/StudentsController.php
    public function update(User $user, ?UploadedFile $photo, bool $removePhoto): void
    {
        if (! $photo && ! $removePhoto) {
            return;
        }

        $previousPath = $user->profile_photo_path;
        $newPath = $photo?->store('profile-photos', 'public');

        if ($photo && ! $newPath) {
            throw ValidationException::withMessages([
                'profile_photo' => 'The profile picture could not be uploaded. Please try again.',
            ]);
        }

        $user->forceFill([
            'profile_photo_path' => $newPath,
        ])->save();

        if ($previousPath && $previousPath !== $newPath) {
            Storage::disk('public')->delete($previousPath);
        }
    }
}
