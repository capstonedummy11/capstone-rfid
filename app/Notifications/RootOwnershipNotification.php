<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RootOwnershipNotification extends Notification implements ShouldQueue
{
    use Queueable;

    // @function __construct: Tinatanggap ang dependencies ng Root Ownership Notification sa pagbuo ng object.
    // @useIn __construct: Laravel dependency injection kapag ginagamit ang RootOwnershipNotification
    public function __construct(private readonly string $event, private readonly array $details) {}

    // @function via: Kinukuha ang via result para sa Root Ownership Notification.
    // @useIn via: Laravel notification delivery
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    // @function toMail: Kinukuha ang to mail result para sa Root Ownership Notification.
    // @useIn toMail: Laravel notification delivery
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->details['subject'] ?? 'Root Admin ownership update')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->details['message'] ?? 'The Root Admin ownership request was updated.');

        foreach (['requested_by' => 'Requested by', 'requested_at' => 'Requested at', 'ip' => 'IP address', 'user_agent' => 'Device', 'effective_at' => 'Effective at'] as $key => $label) {
            if (! empty($this->details[$key])) {
                $mail->line($label.': '.$this->details[$key]);
            }
        }

        if (! empty($this->details['accept_url'])) {
            $mail->action('Review and accept transfer', $this->details['accept_url']);
        } elseif (! empty($this->details['cancel_url'])) {
            $mail->action('Cancel this transfer', $this->details['cancel_url']);
        }

        if (! empty($this->details['cancel_url']) && ! empty($this->details['accept_url'])) {
            $mail->line('Cancel link: '.$this->details['cancel_url']);
        }

        return $mail->line('Event: '.str_replace('_', ' ', $this->event));
    }
}
