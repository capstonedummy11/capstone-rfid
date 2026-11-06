<?php

namespace App\Notifications;

use App\Models\EmergencyAlert;
use App\Models\Students;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmergencyParentAlert extends Notification
{
    use Queueable;

    // @function __construct: Tinatanggap ang dependencies ng Emergency Parent Alert sa pagbuo ng object.
    // @useIn __construct: Laravel dependency injection kapag ginagamit ang EmergencyParentAlert
    public function __construct(
        private readonly EmergencyAlert $alert,
        private readonly Students $student,
    ) {}

    // @function via: Kinukuha ang via result para sa Emergency Parent Alert.
    // @useIn via: Laravel notification delivery
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    // @function toMail: Kinukuha ang to mail result para sa Emergency Parent Alert.
    // @useIn toMail: Laravel notification delivery
    public function toMail(object $notifiable): MailMessage
    {
        $studentName = trim($this->student->first_name.' '.$this->student->last_name);

        $message = (new MailMessage)
            ->subject('Emergency alert for '.$studentName)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('An emergency was reported for your linked student.')
            ->line('Student: '.$studentName)
            ->line('Student number: '.($this->student->student_number ?: 'Not provided'))
            ->line('Emergency: '.($this->alert->type?->name ?? 'Emergency'))
            ->line('Location: '.($this->alert->room ?: 'Not provided'))
            ->line('Details: '.($this->alert->message ?: 'No additional details were provided.'));

        $symptoms = trim((string) ($this->alert->metadata['symptoms'] ?? ''));
        if ($symptoms !== '') {
            $message->line('Symptoms / notes: '.$symptoms);
        }

        return $message->line('Please contact the school or clinic immediately for further information.');
    }
}
