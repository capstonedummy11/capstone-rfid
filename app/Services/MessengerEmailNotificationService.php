<?php

namespace App\Services;

use App\Models\StudentPortalMessage;
use App\Models\User;
use App\Notifications\MessengerMessageReceived;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MessengerEmailNotificationService
{
    public function notify(User $sender, User $recipient, StudentPortalMessage $message): bool
    {
        if (! filter_var($recipient->email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $cooldownMinutes = max(1, (int) config('messenger.email_notification_cooldown_minutes', 5));
        $cacheKey = "messenger-email-notification:{$sender->user_id}:{$recipient->user_id}";

        if (! Cache::add($cacheKey, true, now()->addMinutes($cooldownMinutes))) {
            return false;
        }

        try {
            $recipient->notify(new MessengerMessageReceived($sender, $message));

            return true;
        } catch (\Throwable $exception) {
            Cache::forget($cacheKey);
            Log::warning('Messenger email notification could not be sent.', [
                'message_id' => $message->student_portal_message_id,
                'sender_user_id' => $sender->user_id,
                'recipient_user_id' => $recipient->user_id,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
