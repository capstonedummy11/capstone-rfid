<?php

namespace Database\Seeders;

use App\Models\Message;
use App\Models\Students;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class MessageSeeder extends Seeder
{
    public function run(): void
    {
        $student = Students::query()
            ->where('student_number', 'SHS-ICT-1101')
            ->first()
            ?? Students::query()->orderBy('student_id')->first();

        $instructor = User::query()
            ->where('email', 'instructor@sample.com')
            ->whereRaw('LOWER(role) = ?', ['instructor'])
            ->first()
            ?? User::query()->whereRaw('LOWER(role) = ?', ['instructor'])->orderBy('user_id')->first();

        if (! $student || ! $instructor) {
            return;
        }

        $attachmentPath = 'message-attachments/demo-attendance-note.pdf';
        $attachmentContents = implode("\n", [
            '%PDF-1.4',
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
            '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 300 144] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj',
            '4 0 obj << /Length 93 >> stream',
            'BT /F1 12 Tf 24 104 Td (Demo attendance concern attachment.) Tj 0 -18 Td (Please review the student note.) Tj ET',
            'endstream endobj',
            '5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
            'xref',
            '0 6',
            '0000000000 65535 f ',
            'trailer << /Root 1 0 R /Size 6 >>',
            'startxref',
            '0',
            '%%EOF',
        ]);

        Storage::disk('public')->put($attachmentPath, $attachmentContents);

        $senderName = trim($student->first_name . ' ' . $student->last_name);

        Message::query()->updateOrCreate(
            [
                'instructor_user_id' => $instructor->user_id,
                'student_number' => $student->student_number,
                'subject' => 'Demo attendance concern with attachment',
            ],
            [
                'sender_type' => 'student',
                'sender_name' => $senderName,
                'sender_email' => $student->email,
                'body' => "Good day, Sir/Ma'am,\n\nI am sending this sample message to demonstrate an attendance concern with an attached file for review.",
                'attachment_path' => $attachmentPath,
                'attachment_name' => 'demo-attendance-note.pdf',
                'attachment_mime' => 'application/pdf',
                'attachment_size' => Storage::disk('public')->size($attachmentPath),
                'read_at' => null,
            ],
        );
    }
}

