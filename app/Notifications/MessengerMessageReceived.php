<?php

namespace App\Notifications;

use App\Models\StudentPortalMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MessengerMessageReceived extends Notification
{
    use Queueable;

    // @function __construct: Tinatanggap ang dependencies ng Messenger Message Received sa pagbuo ng object.
    // @useIn __construct: Laravel dependency injection kapag ginagamit ang MessengerMessageReceived
    public function __construct(
        private readonly User $sender,
        private readonly StudentPortalMessage $message,
    ) {}

    // @function via: Kinukuha ang via result para sa Messenger Message Received.
    // @useIn via: Laravel notification delivery
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    // @function toMail: Kinukuha ang to mail result para sa Messenger Message Received.
    // @useIn toMail: Laravel notification delivery
    public function toMail(object $notifiable): MailMessage
    {
        $preview = str($this->message->body ?: $this->message->attachment_name ?: 'Attachment')
            ->squish()
            ->limit(180)
            ->toString();

        return (new MailMessage)
            ->subject('New Messenger message from '.$this->sender->name)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->sender->name.' sent you a new Messenger message.')
            ->line($preview)
            ->action('Open Messenger', route('messages.index'))
            ->line('Additional messages from this sender may be grouped into this notification to prevent excessive email.');
    }
}
