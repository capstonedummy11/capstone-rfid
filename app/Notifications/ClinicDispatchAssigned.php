<?php

namespace App\Notifications;

use App\Models\ClinicCase;
use App\Models\EmergencyAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClinicDispatchAssigned extends Notification
{
    use Queueable;

    // @function __construct: Tinatanggap ang dependencies ng Clinic Dispatch Assigned sa pagbuo ng object.
    // @useIn __construct: Laravel dependency injection kapag ginagamit ang ClinicDispatchAssigned
    public function __construct(
        private readonly EmergencyAlert $alert,
        private readonly ClinicCase $clinicCase,
        private readonly array $historySummary,
    ) {}

    // @function via: Kinukuha ang via result para sa Clinic Dispatch Assigned.
    // @useIn via: Laravel notification delivery
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    // @function toMail: Kinukuha ang to mail result para sa Clinic Dispatch Assigned.
    // @useIn toMail: Laravel notification delivery
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Clinic dispatch assignment: '.$this->clinicCase->patient_name)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('You have been assigned to respond to a clinic emergency.')
            ->line('Location: '.($this->alert->room ?: 'No location provided'))
            ->line('Student/Patient: '.$this->clinicCase->patient_name)
            ->line('Emergency: '.$this->clinicCase->case_type)
            ->line('Symptoms/Details: '.($this->clinicCase->symptoms ?: 'No details provided'));

        foreach ($this->historySummary as $line) {
            $mail->line($line);
        }

        return $mail
            ->line('Please proceed to the dispatch location and continue documentation in Clinic Case Logs.')
            ->action('Open Clinic Case Logs', route('clinic.case-logs'));
    }
}
