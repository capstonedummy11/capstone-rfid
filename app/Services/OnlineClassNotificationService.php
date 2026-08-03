<?php

namespace App\Services;

use App\Models\OnlineClass;
use App\Models\OnlineClassNotification;
use App\Models\Students;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OnlineClassNotificationService
{
    public function __construct(private OnlineClassAuditLogger $auditLogger) {}

    public function notifyStudents(OnlineClass $onlineClass, string $event): void
    {
        $onlineClass->loadMissing(['section', 'subject', 'instructor.user']);

        $students = Students::query()
            ->when($onlineClass->academic_year_id,
                fn ($query) => $query->whereHas('enrollments', fn ($enrollment) => $enrollment
                    ->where('academic_year_id', $onlineClass->academic_year_id)->where('section_id', $onlineClass->section_id)->where('status', 'active')),
                fn ($query) => $query->where('section_id', $onlineClass->section_id))
            ->whereNotNull('email')
            ->get();

        foreach ($students as $student) {
            $enrollmentId = $onlineClass->academic_year_id ? $student->enrollments()
                ->where('academic_year_id', $onlineClass->academic_year_id)->where('section_id', $onlineClass->section_id)
                ->value('student_enrollment_id') : null;
            $notification = OnlineClassNotification::query()->create([
                'online_class_id' => $onlineClass->online_class_id,
                'student_id' => $student->student_id,
                'academic_year_id' => $onlineClass->academic_year_id,
                'subject_offering_id' => $onlineClass->subject_offering_id,
                'student_enrollment_id' => $enrollmentId,
                'event' => $event,
                'title' => $this->title($onlineClass, $event),
                'body' => $this->body($onlineClass, $event),
            ]);

            $this->auditLogger->log('in_app_notification_sent', $onlineClass, new \App\Models\User([
                'role' => 'system',
            ]), null, null, [
                'student_id' => $student->student_id,
                'notification_id' => $notification->online_class_notification_id,
                'event' => $event,
            ]);

            try {
                Mail::raw($notification->body, function ($message) use ($student, $notification) {
                    $message->to($student->email)
                        ->subject($notification->title);
                });

                $notification->update(['email_sent_at' => now()]);
                $this->auditLogger->log('email_notification_sent', $onlineClass, null, null, null, [
                    'student_id' => $student->student_id,
                    'email' => $student->email,
                    'event' => $event,
                ]);
            } catch (Throwable $exception) {
                $notification->update(['email_error' => $exception->getMessage()]);
            }
        }
    }

    private function title(OnlineClass $onlineClass, string $event): string
    {
        return 'Online class '.str_replace('_', ' ', $event).': '.$onlineClass->title;
    }

    private function body(OnlineClass $onlineClass, string $event): string
    {
        return implode("\n", [
            'Online class '.str_replace('_', ' ', $event).'.',
            'Class: '.$onlineClass->section?->section_name,
            'Subject: '.($onlineClass->subject?->subject_name ?? $onlineClass->subject_code),
            'Instructor: '.($onlineClass->instructor?->user?->name ?? 'Instructor'),
            'Schedule: '.$onlineClass->scheduled_date?->format('Y-m-d').' '.$onlineClass->start_time.'-'.$onlineClass->end_time,
            'Meeting link: '.$onlineClass->meeting_link,
            'Facial recognition required: '.($onlineClass->require_face_recognition ? 'Yes' : 'No'),
        ]);
    }
}
