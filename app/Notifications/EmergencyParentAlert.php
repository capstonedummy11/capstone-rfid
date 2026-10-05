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

    public function __construct(
        private readonly EmergencyAlert $alert,
        private readonly Students $student,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $studentName = trim($this->student->first_name.' '.$this->student->last_name);

        return (new MailMessage)
            ->subject('Emergency alert for '.$studentName)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('An emergency was reported for your linked student.')
            ->line('Student: '.$studentName)
            ->line('Student number: '.($this->student->student_number ?: 'Not provided'))
            ->line('Emergency: '.($this->alert->type?->name ?? 'Emergency'))
            ->line('Location: '.($this->alert->room ?: 'Not provided'))
            ->line('Details: '.($this->alert->message ?: 'No additional details were provided.'))
            ->line('Please contact the school or clinic immediately for further information.');
    }
}
